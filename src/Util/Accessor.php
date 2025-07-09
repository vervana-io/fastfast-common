<?php

namespace Fastfast\Common\Util;

use Symfony\Component\PropertyAccess\PropertyAccess;

class Accessor
{
    public static function getValue(array $object, string $key, mixed $default = null): mixed
    {
        $property = PropertyAccess::createPropertyAccessor();
        return  $property->isReadable($object, $key) ?
           $property->getValue($object, $key) : $default;
    }
}
