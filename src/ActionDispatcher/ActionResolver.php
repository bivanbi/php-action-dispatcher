<?php


namespace KignOrg\ActionDispatcher;


use KignOrg\ActionDispatcher\Exceptions\AmbiguousActionReceiverException;
use KignOrg\ActionDispatcher\Exceptions\ClassNotFoundException;
use KignOrg\ActionDispatcher\Exceptions\IllegalActionException;
use KignOrg\ActionDispatcher\Exceptions\IllegalCallableException;
use KignOrg\ActionDispatcher\Exceptions\IllegalClassException;
use KignOrg\ActionDispatcher\Exceptions\NoAcceptedActionDefinedException;
use KignOrg\ActionDispatcher\Exceptions\NoReceiverDefinedException;
use KignOrg\ActionDispatcher\Interfaces\ActionReceiver;
use KignOrg\ActionDispatcher\Interfaces\ActionReceiverStaticCreator;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;

class ActionResolver
{
    private ?array $receivers;
    private bool $permitAmbiguousReceiver;
    private array $actionMap;

    /**
     * ActionResolver constructor.
     * @param array|null $receivers
     * @param bool $permitAmbiguousReceiver
     * @throws AmbiguousActionReceiverException
     * @throws ClassNotFoundException
     * @throws IllegalClassException
     * @throws ReflectionException
     * @throws NoAcceptedActionDefinedException
     * @throws NoReceiverDefinedException
     * @throws IllegalCallableException
     */
    public function __construct(?array $receivers, bool $permitAmbiguousReceiver = false)
    {
        $this->receivers = array_unique($receivers);
        $this->permitAmbiguousReceiver = $permitAmbiguousReceiver;
        $this->initialize();
    }

    /**
     * @throws AmbiguousActionReceiverException
     * @throws ClassNotFoundException
     * @throws IllegalClassException
     * @throws ReflectionException
     * @throws NoAcceptedActionDefinedException
     * @throws NoReceiverDefinedException
     * @throws IllegalCallableException
     */
    private function initialize(): void
    {
        $this->actionMap = [];
        $this->exceptOnEmptyReceivers();
        $this->exceptOnInvalidReceivers();
        $this->instantiateReceivers();
        $this->exceptOnEmptyAcceptedActions();
        $this->makeActionMap();
    }

    /**
     * @param string $action
     * @return array
     * @throws IllegalActionException
     */
    public function resolve(string $action): array
    {
        $this->exceptOnInvalidAction($action);
        return $this->actionMap[$action];
    }

    /**
     * @throws NoReceiverDefinedException
     */
    private function exceptOnEmptyReceivers(): void
    {
        if (0 === count($this->receivers)) {
            throw new NoReceiverDefinedException("Receiver array must contain at least one receiver");
        }
    }


    /**
     * @throws NoAcceptedActionDefinedException
     */
    private function exceptOnEmptyAcceptedActions(): void
    {
        /** @var ActionReceiver $receiver */
        foreach ($this->receivers as $receiver) {
            $acceptedActions = $receiver->getAcceptedActions();
            if (0 === count($acceptedActions)) {
                $classString = $this->getObjectClassAndIdString($receiver);
                throw new NoAcceptedActionDefinedException("Receiver '$classString' must accept at least one action");
            }
        }
    }

    /**
     * @throws ClassNotFoundException
     * @throws IllegalClassException
     * @throws ReflectionException
     * @throws IllegalCallableException
     */
    private function exceptOnInvalidReceivers(): void
    {
        foreach ($this->receivers as $receiver) {
            $reflection = $this->getReflection($receiver);
            $interface = $this->getExpectedInterface($receiver);

            if (!$reflection->implementsInterface($interface)) {
                $class = $reflection->getName();
                throw new IllegalClassException("Class '$class' must implement '$interface'");
            }
        }
    }

    private function instantiateReceivers(): void
    {
        foreach ($this->receivers as $index => $receiver) {
            if (is_string($receiver)) {
                $this->receivers[$index] = call_user_func([$receiver, 'getActionReceiver']);
            }
        }
    }


    /**
     * @param $reflection
     * @return ReflectionClass|ReflectionObject
     * @throws ReflectionException
     * @throws ClassNotFoundException
     * @throws IllegalCallableException
     */
    private function getReflection($reflection): ReflectionClass|ReflectionObject
    {
        if (is_object($reflection)) {
            return new ReflectionObject($reflection);
        } else if (is_string($reflection)) {
            return $this->getReflectionClass($reflection);
        } else {
            throw new IllegalCallableException("Receiver must be object or valid class name");
        }
    }


    private function getExpectedInterface($receiver): string
    {
        if (is_object($receiver)) {
            return ActionReceiver::class;
        } else {
            return ActionReceiverStaticCreator::class;
        }
    }

    /**
     * @param string $class
     * @return ReflectionClass
     * @throws ClassNotFoundException
     * @throws ReflectionException
     */
    private function getReflectionClass(string $class): ReflectionClass
    {
        $this->exceptOnNonExistingClass($class);
        return new ReflectionClass($class);
    }

    /**
     * @param string $class
     * @throws ClassNotFoundException
     */
    private function exceptOnNonExistingClass(string $class): void
    {
        if (!class_exists($class)) {
            throw new ClassNotFoundException("Class '$class' does not exist");
        }
    }


    /**
     * @param string $action
     * @throws IllegalActionException
     */
    private function exceptOnInvalidAction(string $action): void
    {
        if (!array_key_exists($action, $this->actionMap)) {
            throw new IllegalActionException("Illegal action '$action'");
        }
    }


    /**
     * @return void
     * @throws AmbiguousActionReceiverException
     */
    private function makeActionMap(): void
    {
        foreach ($this->receivers as $receiver) {
            $acceptedActions = $receiver->getAcceptedActions();
            foreach ($acceptedActions as $action) {
                $this->addActionToMap($action, $receiver);
            }
        }
    }

    /**
     * @param string $action
     * @param ActionReceiver $receiver
     * @return void
     * @throws AmbiguousActionReceiverException
     */
    private function addActionToMap(string $action, ActionReceiver $receiver): void
    {
        $this->initActionMapItem($action);
        $this->exceptOnProhibitedReceiverAmbiguity($action, $receiver);
        $this->actionMap[$action][] = $receiver;
    }

    private function initActionMapItem(string $action): void
    {
        if (!array_key_exists($action, $this->actionMap)) {
            $this->actionMap[$action] = [];
        }
    }

    /**
     * @param string $action
     * @param ActionReceiver $receiver
     * @throws AmbiguousActionReceiverException
     */
    private function exceptOnProhibitedReceiverAmbiguity(string $action, ActionReceiver $receiver): void
    {
        if ($this->isMultipleReceiverProhibited() && count($this->actionMap[$action])) {
            $receiverString = $this->getObjectClassAndIdString($receiver);
            $conflictingReceiverString = $this->getObjectClassAndIdString(array_values($this->actionMap[$action])[0]);
            throw new AmbiguousActionReceiverException("Ambiguous receiver for '$action': '$conflictingReceiverString', '$receiverString'");
        }
    }

    private function getObjectClassAndIdString(Object $object): string
    {
        $class = get_class($object);
        $id = spl_object_id($object);
        return "$class#$id";
    }

    private function isMultipleReceiverProhibited(): bool
    {
        return !$this->permitAmbiguousReceiver;
    }

}
