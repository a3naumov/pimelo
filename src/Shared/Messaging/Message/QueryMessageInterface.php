<?php

declare(strict_types=1);

namespace Pimelo\Shared\Messaging\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'sync')]
interface QueryMessageInterface
{

}
