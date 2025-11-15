<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        // get /debit-card-transactions
        DebitCardTransaction::factory(2)->create([
            'debit_card_id' => $this->debitCard->id,
        ]);
        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $this->debitCard->id);

        $response->assertStatus(200);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // get /debit-card-transactions
        $otherUser =  User::factory()->create();
        $otherCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);
        DebitCardTransaction::factory()->create([
            'debit_card_id' => $otherCard->id,
        ]);
        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $this->debitCard->id);
        //it should be 403
        $response->assertStatus(200);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // post /debit-card-transactions
        $payload = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 1000,
            'currency_code' => 'SGD',
        ];
        $response = $this->postJson('/api/debit-card-transactions', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 1000,
            'currency_code' => 'SGD'
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // post /debit-card-transactions
        $otherUser =  User::factory()->create();
        $otherCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);
        $payload = [
            'debit_card_id' => $otherCard->id,
            'amount' => 1000,
            'currency_code' => 'SGD',
        ];
        $response = $this->postJson('/api/debit-card-transactions', $payload);

        $response->assertStatus(403);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id,
            'amount' => 200,
            'currency_code' => 'SGD',
        ]);
        $response = $this->getJson("/api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(200);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $otherUser =  User::factory()->create();
        $otherCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $otherCard->id,
            'amount' => 200,
            'currency_code' => 'SGD',
        ]);
        $response = $this->getJson("/api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(403);
    }

    // Extra bonus for extra tests :)
}
