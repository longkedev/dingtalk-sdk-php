<?php

declare(strict_types=1);

namespace DingTalk\Services;

/**
 * 消息推送服务
 * 
 * 提供消息发送相关的API操作
 */
class MessageService extends BaseService
{
    /**
     * 发送工作通知消息
     */
    public function sendWorkNotification(array $messageData): array
    {
        $required = ['agent_id', 'userid_list', 'msg'];
        $this->validateRequired($messageData, $required);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->sendV2WorkNotification($messageData);
        }
        
        return $this->sendV1WorkNotification($messageData);
    }

    /**
     * 发送群消息
     */
    public function sendGroupMessage(string $chatId, array $messageData): array
    {
        $this->validateRequired(['chatId' => $chatId] + $messageData, ['chatId', 'msg']);
        
        $messageData['chatid'] = $chatId;
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->sendV2GroupMessage($messageData);
        }
        
        return $this->sendV1GroupMessage($messageData);
    }

    /**
     * 发送机器人消息
     */
    public function sendRobotMessage(string $webhook, array $messageData, string $secret = null): array
    {
        $this->validateRequired($messageData, ['msgtype']);
        
        // 如果有密钥，生成签名
        if ($secret) {
            $timestamp = (string)(time() * 1000);
            $sign = $this->generateRobotSign($timestamp, $secret);
            $webhook .= "&timestamp={$timestamp}&sign={$sign}";
        }
        
        return $this->post($webhook, $messageData, [], false);
    }

    /**
     * 发送普通消息
     */
    public function sendMessage(string $sender, string $cid, array $messageData): array
    {
        $this->validateRequired(['sender' => $sender, 'cid' => $cid] + $messageData, ['sender', 'cid', 'msg']);
        
        $messageData['sender'] = $sender;
        $messageData['cid'] = $cid;
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->sendV2Message($messageData);
        }
        
        return $this->sendV1Message($messageData);
    }

    /**
     * 撤回消息
     */
    public function recallMessage(string $msgId): array
    {
        $this->validateRequired(['msgId' => $msgId], ['msgId']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->recallV2Message($msgId);
        }
        
        return $this->recallV1Message($msgId);
    }

    /**
     * 查询消息发送状态
     */
    public function getMessageStatus(string $taskId): array
    {
        $this->validateRequired(['taskId' => $taskId], ['taskId']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->getV2MessageStatus($taskId);
        }
        
        return $this->getV1MessageStatus($taskId);
    }

    /**
     * 获取消息发送结果
     */
    public function getMessageResult(string $taskId): array
    {
        $this->validateRequired(['taskId' => $taskId], ['taskId']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->getV2MessageResult($taskId);
        }
        
        return $this->getV1MessageResult($taskId);
    }

    /**
     * 创建文本消息
     */
    public function createTextMessage(string $content): array
    {
        return [
            'msgtype' => 'text',
            'text' => [
                'content' => $content,
            ],
        ];
    }

    /**
     * 创建链接消息
     */
    public function createLinkMessage(string $title, string $text, string $messageUrl, string $picUrl = ''): array
    {
        return [
            'msgtype' => 'link',
            'link' => [
                'title' => $title,
                'text' => $text,
                'messageUrl' => $messageUrl,
                'picUrl' => $picUrl,
            ],
        ];
    }

    /**
     * 创建Markdown消息
     */
    public function createMarkdownMessage(string $title, string $text): array
    {
        return [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $title,
                'text' => $text,
            ],
        ];
    }

    /**
     * 创建ActionCard消息
     */
    public function createActionCardMessage(string $title, string $text, array $buttons, string $btnOrientation = '0'): array
    {
        $actionCard = [
            'title' => $title,
            'text' => $text,
            'btnOrientation' => $btnOrientation,
        ];
        
        if (count($buttons) === 1) {
            $actionCard['singleTitle'] = $buttons[0]['title'];
            $actionCard['singleURL'] = $buttons[0]['actionURL'];
        } else {
            $actionCard['btns'] = $buttons;
        }
        
        return [
            'msgtype' => 'actionCard',
            'actionCard' => $actionCard,
        ];
    }

    /**
     * 创建FeedCard消息
     */
    public function createFeedCardMessage(array $links): array
    {
        return [
            'msgtype' => 'feedCard',
            'feedCard' => [
                'links' => $links,
            ],
        ];
    }

    /**
     * 创建图片消息
     */
    public function createImageMessage(string $mediaId): array
    {
        return [
            'msgtype' => 'image',
            'image' => [
                'media_id' => $mediaId,
            ],
        ];
    }

    /**
     * 创建语音消息
     */
    public function createVoiceMessage(string $mediaId, int $duration): array
    {
        return [
            'msgtype' => 'voice',
            'voice' => [
                'media_id' => $mediaId,
                'duration' => $duration,
            ],
        ];
    }

    /**
     * 创建文件消息
     */
    public function createFileMessage(string $mediaId): array
    {
        return [
            'msgtype' => 'file',
            'file' => [
                'media_id' => $mediaId,
            ],
        ];
    }

    /**
     * 创建OA消息
     */
    public function createOAMessage(string $messageUrl, array $head, array $body): array
    {
        return [
            'msgtype' => 'oa',
            'oa' => [
                'message_url' => $messageUrl,
                'head' => $head,
                'body' => $body,
            ],
        ];
    }

    /**
     * 批量发送消息
     */
    public function batchSend(array $messages): array
    {
        return $this->batch($messages, function ($batch) {
            $results = [];
            foreach ($batch as $message) {
                try {
                    if (isset($message['type'])) {
                        switch ($message['type']) {
                            case 'work_notification':
                                $results[] = $this->sendWorkNotification($message['data']);
                                break;
                            case 'group_message':
                                $results[] = $this->sendGroupMessage($message['chatId'], $message['data']);
                                break;
                            case 'robot_message':
                                $results[] = $this->sendRobotMessage($message['webhook'], $message['data'], $message['secret'] ?? null);
                                break;
                            default:
                                throw new \InvalidArgumentException("Unknown message type: {$message['type']}");
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to send message', [
                        'message' => $message,
                        'error' => $e->getMessage(),
                    ]);
                    $results[] = ['error' => $e->getMessage()];
                }
            }
            return $results;
        }, 10);
    }

    /**
     * V1版本：发送工作通知消息
     */
    private function sendV1WorkNotification(array $messageData): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/message/corpconversation/asyncsend_v2', $messageData, [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：发送工作通知消息
     */
    private function sendV2WorkNotification(array $messageData): array
    {
        return $this->post('/v1.0/robot/oToMessages/batchSend', $messageData);
    }

    /**
     * V1版本：发送群消息
     */
    private function sendV1GroupMessage(array $messageData): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/chat/send', $messageData, [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：发送群消息
     */
    private function sendV2GroupMessage(array $messageData): array
    {
        return $this->post('/v1.0/im/chat/messages/send', $messageData);
    }

    /**
     * V1版本：发送普通消息
     */
    private function sendV1Message(array $messageData): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/message/send_to_conversation', $messageData, [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：发送普通消息
     */
    private function sendV2Message(array $messageData): array
    {
        return $this->post('/v1.0/im/conversations/messages/send', $messageData);
    }

    /**
     * V1版本：撤回消息
     */
    private function recallV1Message(string $msgId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/chat/recall', [
            'msg_id' => $msgId,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：撤回消息
     */
    private function recallV2Message(string $msgId): array
    {
        return $this->post('/v1.0/im/chat/messages/recall', [
            'msgId' => $msgId,
        ]);
    }

    /**
     * V1版本：查询消息发送状态
     */
    private function getV1MessageStatus(string $taskId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/message/corpconversation/getsendprogress', [
            'task_id' => $taskId,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：查询消息发送状态
     */
    private function getV2MessageStatus(string $taskId): array
    {
        return $this->get("/v1.0/robot/messages/{$taskId}/sendProgress");
    }

    /**
     * V1版本：获取消息发送结果
     */
    private function getV1MessageResult(string $taskId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/message/corpconversation/getsendresult', [
            'task_id' => $taskId,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：获取消息发送结果
     */
    private function getV2MessageResult(string $taskId): array
    {
        return $this->get("/v1.0/robot/messages/{$taskId}/sendResult");
    }

    /**
     * 生成机器人签名
     */
    private function generateRobotSign(string $timestamp, string $secret): string
    {
        $stringToSign = $timestamp . "\n" . $secret;
        $sign = hash_hmac('sha256', $stringToSign, $secret, true);
        return urlencode(base64_encode($sign));
    }
}