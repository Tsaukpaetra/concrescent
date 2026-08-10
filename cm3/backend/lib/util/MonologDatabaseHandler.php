<?php

namespace CM3_Lib\util;

use CM3_Lib\database\Table;
use Monolog\Formatter\JsonFormatter;
use Psr\Http\Message\ServerRequestInterface;

class MonologDatabaseHandler extends \Monolog\Handler\AbstractProcessingHandler
{
    public function __construct(
        private Table $targetTable,
        $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
        $this->formatter = new JsonFormatter(1, false, false);
    }
    protected function write(array $record): void
    {
        //FormattedRecord array{message: string, context: mixed[], level: Level, level_name: LevelName, channel: string, datetime: \DateTimeImmutable, extra: mixed[], formatted: mixed}
        
        $data = [
            'remote_addr' => $record['extra']['ip'],
            'request_uri' => $record['context']['path'] ?? $record['extra']['url'],
            'http_referrer' => $record['extra']['referrer'] ??'',
            'http_user_agent' => $record['extra']['user_agent'] ?? '[Anonymous]',
            'message' => substr($record['message'], 0, 500),
            'level' => $record['level_name'],
            'channel' => $record['channel'],
            'action' => $record['extra']['http_method'],
            'contact_id' => $record['context']['contact_id'] ?? 0,
            'event_id' => $record['context']['event_id'] ?? 0,
            'status_code' => $record['context']['status_code'] ?? 0,
            'data' => mb_strcut($this->getFormatter()->format($record['context']['data'] ?? []), 0, 65535, 'UTF-8'),
        
        ];
        //Only add duration if it exists in the table
        if ($this->targetTable->HasColumn('server_duration')) {
            $this->targetTable->debugThrowBeforeSelect = true;
            $data['server_duration'] = $record['context']['duration'] ?? 0;
        }


        $this->targetTable->Create($data);
    }
}
