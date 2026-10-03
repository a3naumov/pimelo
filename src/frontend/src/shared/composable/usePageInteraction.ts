import { inject, provide, readonly, ref, type InjectionKey, type Ref } from 'vue';

// Portals live outside the page DOM; use context as well as hidden/inert on the page.
const pageInteractionKey: InjectionKey<Readonly<Ref<boolean>>> = Symbol('pageInteraction');

export function providePageInteraction(enabled: Readonly<Ref<boolean>>): void {
  provide(pageInteractionKey, enabled);
}

export function usePageInteraction(): Readonly<Ref<boolean>> {
  return inject(pageInteractionKey, readonly(ref(true)));
}
