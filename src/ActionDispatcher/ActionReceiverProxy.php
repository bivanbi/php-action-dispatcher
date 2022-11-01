<?php


namespace KignOrg\ActionDispatcher;


use KignOrg\ActionDispatcher\Exceptions\ActionReceiverFailedException;
use KignOrg\ActionDispatcher\Exceptions\ActionTargetNotCallable;
use KignOrg\ActionDispatcher\Exceptions\IllegalActionException;
use KignOrg\ActionDispatcher\Interfaces\ActionReceiver;

class ActionReceiverProxy implements ActionReceiver
{
    private array $actionMap;

    /**
     * ActionReceiverProxy constructor.
     * @param array $actionMap array of string action => callable callback
     */
    public function __construct(array $actionMap)
    {
        $this->actionMap = $actionMap;
    }

    public function getAcceptedActions(): array
    {
        return array_keys($this->actionMap);
    }

    /**
     * @param string $action
     * @param mixed $payload
     * @return bool
     * @throws IllegalActionException
     * @throws ActionReceiverFailedException
     * @throws ActionTargetNotCallable
     */
    public function receiveAction(string $action, $payload): bool
    {
        $this->throwExceptionOnIllegalAction($action);
        $result = call_user_func($this->actionMap[$action], $action, $payload);
        if (true !== $result) {
            $class = CallableHelper::guessName($this->actionMap[$action]);
            throw new ActionReceiverFailedException("Action '$action' receiver '$class' failed: ".json_encode($result));
        }
        return true;
    }

    /**
     * @param string $action
     * @throws IllegalActionException
     * @throws ActionTargetNotCallable
     */
    private function throwExceptionOnIllegalAction(string $action)
    {
        if (!$this->isActionAccepted($action)) {
            throw new IllegalActionException("Illegal action '$action' received");
        }
        if (!is_callable($this->actionMap[$action])) {
            throw new ActionTargetNotCallable("Action '$action' target is not callable");
        }
    }

    private function isActionAccepted(string $action): bool
    {
        return array_key_exists($action, $this->actionMap);
    }

    public function __toString()
    {
        $class = get_class($this);
        $id = spl_object_id($this);
        return "$class#$id";
    }
}
