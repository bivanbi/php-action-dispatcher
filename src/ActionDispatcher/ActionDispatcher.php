<?php


namespace KignOrg\ActionDispatcher;


use KignOrg\ActionDispatcher\Exceptions\ActionReceiverFailedException;
use KignOrg\ActionDispatcher\Exceptions\AmbiguousActionReceiverException;
use KignOrg\ActionDispatcher\Exceptions\ClassNotFoundException;
use KignOrg\ActionDispatcher\Exceptions\IllegalActionException;
use KignOrg\ActionDispatcher\Exceptions\IllegalCallableException;
use KignOrg\ActionDispatcher\Exceptions\IllegalClassException;
use KignOrg\ActionDispatcher\Exceptions\NoAcceptedActionDefinedException;
use KignOrg\ActionDispatcher\Exceptions\NoReceiverDefinedException;
use ReflectionException;

class ActionDispatcher
{
    private ActionResolver $resolver;

    /**
     * ActionDispatcher constructor.
     * @param array $receivers
     * @param bool $permitAmbiguousReceiver
     * @throws AmbiguousActionReceiverException
     * @throws ClassNotFoundException
     * @throws IllegalClassException
     * @throws ReflectionException
     * @throws NoAcceptedActionDefinedException
     * @throws NoReceiverDefinedException
     * @throws IllegalCallableException
     */
    public function __construct(array $receivers, bool $permitAmbiguousReceiver = false)
    {
        $this->resolver = new ActionResolver($receivers, $permitAmbiguousReceiver);
    }

    /**
     * @param string $action
     * @param mixed $payload
     * @return bool
     * @throws IllegalActionException
     * @throws ActionReceiverFailedException
     */
    public function dispatch(string $action, mixed $payload): bool
    {
        $receivers = $this->resolver->resolve($action);
        return ActionReceiverCaller::withReceivers($receivers)
            ->setPayload($payload)
            ->call($action);
    }

    /**
     * @param string $action
     * @return array
     * @throws IllegalActionException
     */
    public function resolveAction(string $action): array
    {
        return $this->resolver->resolve($action);
    }
}
