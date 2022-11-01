<?php


namespace KignOrg\ActionDispatcher;


use KignOrg\ActionDispatcher\Exceptions\ActionReceiverFailedException;
use KignOrg\ActionDispatcher\Interfaces\ActionReceiver;

class ActionReceiverCaller
{
    private array $receivers;
    private mixed $payload;

    private function __construct(array $receivers)
    {
        $this->receivers = $receivers;
    }

    public static function withReceivers(array $receivers): ActionReceiverCaller
    {
        return new ActionReceiverCaller($receivers);
    }

    public function setPayload($payload): ActionReceiverCaller
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * @param string $action
     * @return bool
     * @throws ActionReceiverFailedException
     */
    public function call(string $action): bool
    {
        foreach ($this->receivers as $receiver) {
            $this->callReceiver($action, $receiver);
        }
        return true;
    }

    /**
     * @param string $action
     * @param ActionReceiver $receiver
     * @throws ActionReceiverFailedException
     */
    private function callReceiver(string $action, ActionReceiver $receiver): void
    {
        $result = call_user_func([$receiver, 'receiveAction'], $action, $this->payload);
        if (true !== $result) {
            $class = get_class($receiver);
            throw new ActionReceiverFailedException("Action '$action' receiver '$class' failed: ".json_encode($result));
        }
    }
}
