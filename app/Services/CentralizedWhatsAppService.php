<?php

namespace App\Services;

use App\Traits\ConsumesExternalServices;
use Illuminate\Support\Facades\Log;

class CentralizedWhatsAppService
{
    use ConsumesExternalServices;

    protected $baseUri;

    public function __construct()
    {
        $this->baseUri = rtrim(env('WAPP_CENTRAL_URL', 'http://whatsapp.integracolombia.com/api/v1'), '/') . '/';
    }

    /**
     * Get list of conversations
     * 
     * @param string $token (phone_number_id)
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getConversations(string $token, int $page = 1, int $perPage = 20)
    {
        return $this->makeRequest(
            'GET',
            'conversations',
            [
                'page' => $page,
                'per_page' => $perPage,
            ],
            [],
            ['X-Instance-Token' => $token],
            true
        );
    }

    /**
     * Get messages of a conversation
     * 
     * @param string $token (phone_number_id)
     * @param mixed $conversationId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getMessages(string $token, $conversationId, int $page = 1, int $perPage = 50)
    {
        return $this->makeRequest(
            'GET',
            "conversations/{$conversationId}/messages",
            [
                'page' => $page,
                'per_page' => $perPage,
            ],
            [],
            ['X-Instance-Token' => $token],
            true
        );
    }

    /**
     * Send a text message
     * 
     * @param string $token (phone_number_id)
     * @param string $to
     * @param string $message
     * @return array
     */
    public function sendMessage(string $token, string $to, string $message)
    {
        return $this->makeRequest(
            'POST',
            'messages/send',
            [],
            [
                'to' => $to,
                'message' => $message,
            ],
            ['X-Instance-Token' => $token],
            true
        );
    }

    /**
     * Send a template message
     * 
     * @param string $token (phone_number_id)
     * @param string $to
     * @param string $templateName
     * @param string $languageCode
     * @param array $components
     * @return array
     */
    public function sendTemplate(string $token, string $to, string $templateName, string $languageCode = 'es', array $components = [])
    {
        return $this->makeRequest(
            'POST',
            'messages/template',
            [],
            [
                'to' => $to,
                'template_name' => $templateName,
                'language_code' => $languageCode,
                'components' => $components,
            ],
            ['X-Instance-Token' => $token],
            true
        );
    }

    /**
     * Register a message (sync)
     * 
     * @param string $token (phone_number_id)
     * @param array $data
     * @return array
     */
    public function registerMessage(string $token, array $data)
    {
        return $this->makeRequest(
            'POST',
            'messages/register',
            [],
            $data,
            ['X-Instance-Token' => $token],
            true
        );
    }

    public function decodeResponse($response)
    {
        return json_decode($response, true);
    }

    /**
     * Get messages with status filters
     * 
     * @param string $token (phone_number_id)
     * @param array $filters ['status' => 'delivered', 'has_invoice' => true, etc.]
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getMessagesWithStatus(string $token, array $filters = [], int $page = 1, int $perPage = 100)
    {
        $queryParams = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if (isset($filters['status'])) {
            $queryParams['status'] = $filters['status'];
        }

        if (isset($filters['has_invoice']) && $filters['has_invoice']) {
            $queryParams['has_invoice'] = 1;
        }

        if (isset($filters['has_payment']) && $filters['has_payment']) {
            $queryParams['has_payment'] = 1;
        }

        return $this->makeRequest(
            'GET',
            'messages',
            $queryParams,
            [],
            ['X-Instance-Token' => $token],
            true
        );
    }

    /**
     * Get message status updates (recent status changes)
     * 
     * @param string $token (phone_number_id)
     * @param string|null $since ISO8601 timestamp
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getMessageStatusUpdates(string $token, ?string $since = null, int $page = 1, int $perPage = 100)
    {
        $queryParams = [
            'page' => $page,
            'per_page' => $perPage,
            'status_updates' => 1,
        ];

        if ($since) {
            $queryParams['since'] = $since;
        }

        return $this->makeRequest(
            'GET',
            'messages/status-updates',
            $queryParams,
            [],
            ['X-Instance-Token' => $token],
            true
        );
    }

    /**
     * Search messages with specific relations (invoice, payment, contract)
     * 
     * @param string $token (phone_number_id)
     * @param array $filters ['invoice_id' => int, 'payment_id' => int, 'contract_id' => int]
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function searchMessagesByRelations(string $token, array $filters = [], int $page = 1, int $perPage = 100)
    {
        $queryParams = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if (isset($filters['invoice_id'])) {
            $queryParams['incoming_invoice_id'] = $filters['invoice_id'];
        }
        if (isset($filters['payment_id'])) {
            $queryParams['incoming_payment_id'] = $filters['payment_id'];
        }
        if (isset($filters['contract_id'])) {
            $queryParams['incoming_contract_id'] = $filters['contract_id'];
        }

        return $this->makeRequest(
            'GET',
            'messages/search',
            $queryParams,
            [],
            ['X-Instance-Token' => $token],
            true
        );
    }

    /**
     * Search messages by text content
     * 
     * @param string $token (phone_number_id)
     * @param string $query Text to search
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function searchMessagesByText(string $token, string $query, int $page = 1, int $perPage = 100)
    {
        return $this->makeRequest(
            'GET',
            'messages/search',
            [
                'page' => $page,
                'per_page' => $perPage,
                'q' => $query,
            ],
            [],
            ['X-Instance-Token' => $token],
            true
        );
    }

    /**
     * Search conversations that contain messages matching criteria
     * 
     * @param string $token (phone_number_id)
     * @param array $options ['text' => string, 'has_invoice' => bool, 'has_payment' => bool, 'has_contract' => bool, 'invoice_id' => int, 'payment_id' => int, 'contract_id' => int]
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function searchConversations(string $token, array $options = [], int $page = 1, int $perPage = 50)
    {
        $queryParams = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if (isset($options['text']) && !empty($options['text'])) {
            $queryParams['q'] = $options['text'];
        }
        if (isset($options['has_invoice']) && $options['has_invoice']) {
            $queryParams['has_invoice'] = 1;
        }
        if (isset($options['has_payment']) && $options['has_payment']) {
            $queryParams['has_payment'] = 1;
        }
        if (isset($options['has_contract']) && $options['has_contract']) {
            $queryParams['has_contract'] = 1;
        }
        if (isset($options['invoice_id'])) {
            $queryParams['invoice_id'] = $options['invoice_id'];
        }
        if (isset($options['payment_id'])) {
            $queryParams['payment_id'] = $options['payment_id'];
        }
        if (isset($options['contract_id'])) {
            $queryParams['contract_id'] = $options['contract_id'];
        }

        return $this->makeRequest(
            'GET',
            'conversations/search',
            $queryParams,
            [],
            ['X-Instance-Token' => $token],
            true
        );
    }

    /**
     * Custom resolve authorization to avoid default behavior if any
     */
    public function resolveAuthorization(&$queryParams, &$formParams, &$headers)
    {
        // Token is handled via headers in each request method
    }
}
