<?php

declare(strict_types=1);

namespace App\General\Adapter\Symfony\Http\Error;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.http.exception_status_mapper')]
interface ExceptionStatusMapperInterface
{
    public function statusFor(\Throwable $exception): ?int;
}
