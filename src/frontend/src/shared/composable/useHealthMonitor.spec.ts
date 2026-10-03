import { defineComponent, h } from 'vue';
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { focusManager, QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { getHealthcheck, type HealthcheckResponse } from '@/shared/api/healthcheck';
import { useHealthMonitor } from './useHealthMonitor';

vi.mock('@/shared/api/healthcheck', () => ({ getHealthcheck: vi.fn<typeof getHealthcheck>() }));
const healthy: HealthcheckResponse = {
  service: 'gateway',
  status: 'ok',
  services: { pim: { status: 'ok' } },
};
let wrapper: VueWrapper;
let client: QueryClient;

async function start() {
  let monitor!: ReturnType<typeof useHealthMonitor>;
  client = new QueryClient();
  wrapper = mount(
    defineComponent({
      setup() {
        monitor = useHealthMonitor(['pim']);

        return () => h('div');
      },
    }),
    { global: { plugins: [[VueQueryPlugin, { queryClient: client }]] } },
  );
  await flushPromises();

  return monitor;
}

beforeEach(() => {
  vi.useFakeTimers({
    toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'],
  });
  focusManager.setFocused(true);
  vi.mocked(getHealthcheck).mockResolvedValue(healthy);
});

afterEach(() => {
  wrapper?.unmount();
  client?.clear();
  focusManager.setFocused(undefined);
  vi.useRealTimers();
  vi.resetAllMocks();
});

describe('Application healthcheck polling', () => {
  it('polls every 15 seconds normally and every 5 seconds until recovery', async () => {
    const monitor = await start();
    expect(monitor.isAvailable('pim')).toBe(true);
    expect(getHealthcheck).toHaveBeenCalledTimes(1);
    vi.mocked(getHealthcheck).mockRejectedValueOnce(new Error('offline'));
    await vi.advanceTimersByTimeAsync(15000);
    expect(monitor.isAvailable('pim')).toBe(false);
    expect(getHealthcheck).toHaveBeenCalledTimes(2);
    await vi.advanceTimersByTimeAsync(5000);
    expect(monitor.isAvailable('pim')).toBe(false);
    await vi.advanceTimersByTimeAsync(5000);
    expect(monitor.isAvailable('pim')).toBe(true);
    expect(getHealthcheck).toHaveBeenCalledTimes(4);
    await vi.advanceTimersByTimeAsync(5000);
    expect(getHealthcheck).toHaveBeenCalledTimes(4);
  });

  it('deduplicates manual checks while a request is in flight', async () => {
    let resolve!: (value: HealthcheckResponse) => void;
    vi.mocked(getHealthcheck).mockReturnValue(
      new Promise((done) => {
        resolve = done;
      }),
    );
    const monitor = await start();
    monitor.checkNow();
    monitor.checkNow();
    await flushPromises();
    expect(getHealthcheck).toHaveBeenCalledTimes(1);
    resolve(healthy);
    await flushPromises();
    expect(monitor.isAvailable('pim')).toBe(true);
  });

  it('blocks on offline and checks immediately on reconnect', async () => {
    const monitor = await start();
    window.dispatchEvent(new Event('offline'));
    expect(monitor.isAvailable('pim')).toBe(false);
    window.dispatchEvent(new Event('online'));
    await flushPromises();
    expect(getHealthcheck).toHaveBeenCalledTimes(2);
    expect(monitor.isAvailable('pim')).toBe(false);
    await vi.advanceTimersByTimeAsync(5000);
    expect(monitor.isAvailable('pim')).toBe(true);
  });

  it('aborts on disposal and ignores late replies without leaving timers', async () => {
    let signal: AbortSignal | undefined;
    let resolve!: (value: HealthcheckResponse) => void;
    vi.mocked(getHealthcheck).mockImplementation((value) => {
      signal = value;

      return new Promise((done) => {
        resolve = done;
      });
    });
    const monitor = await start();
    wrapper.unmount();
    expect(signal?.aborted).toBe(true);
    resolve(healthy);
    await flushPromises();
    await vi.advanceTimersByTimeAsync(30000);
    window.dispatchEvent(new Event('online'));
    expect(getHealthcheck).toHaveBeenCalledTimes(1);
    expect(monitor.isAvailable('pim')).toBe(false);
  });
});
