<?php

use App\Models\UploadedFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores a private ai upload and returns preview metadata', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/api/v1/ai/uploads', [
        'file' => HttpUploadedFile::fake()->image('receipt.png', 1200, 800),
        'purpose' => 'receipt',
        'custom_attributes' => [
            'source' => 'chat-composer',
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('upload.purpose', 'receipt')
        ->assertJsonPath('upload.file_type_family', 'image')
        ->assertJsonPath('upload.is_previewable', true)
        ->assertJsonPath('upload.custom_attributes.source', 'chat-composer');

    $uploadedFile = UploadedFile::query()->firstOrFail();

    expect($uploadedFile->user_id)->toBe($user->id)
        ->and($uploadedFile->purpose)->toBe('receipt')
        ->and($uploadedFile->attachable_type)->toBeNull()
        ->and($uploadedFile->attachable_id)->toBeNull();

    Storage::disk('local')->assertExists($uploadedFile->path);
});

it('does not let another user view a private upload', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $response = $this->actingAs($owner)->post('/api/v1/ai/uploads', [
        'file' => HttpUploadedFile::fake()->create('bill.pdf', 500, 'application/pdf'),
        'purpose' => 'bill',
    ]);

    $uploadedFileId = $response->json('upload.id');

    $this->actingAs($otherUser)
        ->get(route('api.ai.uploads.show', ['uploadedFile' => $uploadedFileId]))
        ->assertNotFound();
});
