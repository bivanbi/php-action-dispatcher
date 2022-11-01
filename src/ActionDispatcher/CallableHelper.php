<?php


namespace KignOrg\ActionDispatcher;


class CallableHelper
{
    public static function guessName(callable $callable): bool|string
    {
        if (is_string($callable)) {
            return 'class ' . $callable;
        } elseif (is_object($callable)) {
            return 'object ' . get_class((object)$callable);
        } else {
            return static::guessClassOrObjectCallableName($callable);
        }
    }

    public static function guessClassOrObjectCallableName(callable $callable): bool|string
    {
        $classOrObject = $callable[0];
        $method = $callable[1];

        if (is_string($classOrObject)) {
            return $classOrObject . '::' . $method;
        } elseif (is_object($classOrObject)) {
            return $classOrObject . '->' . $method;
        } else {
            return json_encode($callable);
        }
    }
}
