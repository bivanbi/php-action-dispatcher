<?php


namespace KignOrg\ActionDispatcher\Interfaces;


interface ActionReceiver
{
    public function getAcceptedActions(): array;
    public function receiveAction(string $action, $payload): bool;
    public function __toString();
}
