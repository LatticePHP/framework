<?php

declare(strict_types=1);

namespace Lattice\Mcp\Tests\Fixtures;

use Lattice\Mcp\Attributes\Prompt;
use Lattice\Mcp\Attributes\PromptArgument;
use Lattice\Mcp\Attributes\Resource;
use Lattice\Mcp\Attributes\Tool;
use Lattice\Mcp\Attributes\ToolParam;

final class ContactService
{
    #[Tool(name: 'create_contact', description: 'Creates a new CRM contact')]
    public function create(
        #[ToolParam(description: 'The first name')]
        string $firstName,
        #[ToolParam(description: 'The last name')]
        string $lastName,
        #[ToolParam(description: 'The email address')]
        string $email,
    ): array {
        return [
            'id' => 1,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
        ];
    }

    #[Tool(name: 'search_contacts', description: 'Search contacts by query')]
    public function search(
        string $query,
        int $limit = 10,
    ): array {
        return [
            ['id' => 1, 'name' => 'John Doe'],
            ['id' => 2, 'name' => 'Jane Smith'],
        ];
    }

    #[Tool(description: 'Delete a contact by ID')]
    public function deleteContact(int $id): array
    {
        return ['deleted' => true, 'id' => $id];
    }

    #[Resource(uri: 'contacts://{id}', description: 'A CRM contact', mimeType: 'application/json')]
    public function find(int $id): array
    {
        return [
            'id' => $id,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
        ];
    }

    #[Resource(uri: 'config://app/settings', name: 'appSettings', description: 'Application settings')]
    public function settings(): array
    {
        return [
            'appName' => 'Test CRM',
            'version' => '1.0.0',
        ];
    }

    #[Prompt(name: 'summarize_contact', description: 'Summarize a contact record')]
    public function summarize(
        #[PromptArgument(description: 'The contact ID')]
        string $contactId,
    ): array {
        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => 'Please summarize the contact with ID ' . $contactId,
                ],
            ],
        ];
    }

    #[Prompt(name: 'draft_email', description: 'Draft an email to a contact')]
    public function draftEmail(
        #[PromptArgument(description: 'The recipient name')]
        string $recipientName,
        #[PromptArgument(description: 'The email subject')]
        string $subject,
        #[PromptArgument(description: 'The tone', required: false)]
        string $tone = 'professional',
    ): array {
        return [
            [
                'role' => 'system',
                'content' => [
                    'type' => 'text',
                    'text' => 'You are a helpful email assistant. Write in a ' . $tone . ' tone.',
                ],
            ],
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => 'Draft an email to ' . $recipientName . ' about: ' . $subject,
                ],
            ],
        ];
    }

    /**
     * This method has NO attribute — should NOT be discovered.
     */
    public function internalMethod(): void
    {
    }
}
