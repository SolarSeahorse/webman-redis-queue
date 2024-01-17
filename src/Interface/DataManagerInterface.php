<?php

namespace SolarSeahorse\WebmanRedisQueue\Interface;

interface DataManagerInterface
{
    public function insertAckFailedMessages($data);

    public function getAckFailedMessage($messageId);

    public function deleteAckFailedMessages($messageId);

    public function insertDeadLetters($data);
}