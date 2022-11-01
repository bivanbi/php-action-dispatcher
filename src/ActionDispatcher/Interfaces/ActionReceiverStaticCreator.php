<?php


namespace KignOrg\ActionDispatcher\Interfaces;


interface ActionReceiverStaticCreator
{
    public static function getActionReceiver(): ActionReceiver;
}
