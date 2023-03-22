<?php

namespace LiveIntent\LaravelCommon\Util;

final class StringUtil
{
    private function __construct()
    {
    }

    /**
     * @param string $message
     * @param int $messageMaxSizeBytes
     * @return array<string>
     */
    public static function toMultipart(string $message, int $messageMaxSizeBytes): array
    {
        $splitMessages = [];

        $index = 0;
        while (strlen($message) > (($index + 1) * $messageMaxSizeBytes)) {
            $splitMessages[] = substr($message, ($index * $messageMaxSizeBytes), $messageMaxSizeBytes);
            $index++;
        }

        $splitMessages[] = substr($message, ($index * $messageMaxSizeBytes), $messageMaxSizeBytes);

        return $splitMessages;
    }
}
