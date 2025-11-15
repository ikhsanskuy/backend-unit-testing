<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DebitCard;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\DB;
use App\Models\DebitCardTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // get /debit-cards
        DebitCard::factory(2)->active()->create([
            'user_id' => $this->user->id,
        ]);
        $response = $this->actingAs($this->user)->getJson('/api/debit-cards');

        $response->assertStatus(200);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // get /debit-cards
        $otherUser = User::factory()->create();
        DebitCard::factory()->create([
            'user_id' => $otherUser->id,
        ]);
        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200);
    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
        $payload = [
            'type' => 'visa',
        ];
        $response = $this->actingAs($this->user)->postJson('/api/debit-cards', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'type' => 'visa',
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $card = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id,
        ]);
        $response = $this->actingAs($this->user)->getJson("/api/debit-cards/{$card->id}");

        $response->assertStatus(200);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $otherUser = User::factory()->create();
        $card = DebitCard::factory()->create([
            'user_id' => $otherUser->id,
        ]);
        $response = $this->getJson("/api/debit-cards/{$card->id}");

        $response->assertStatus(403);
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $card = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/debit-cards/{$card->id}", ['is_active' => true]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('debit_cards',[
            'user_id' => $this->user->id,
            'disabled_at' => null
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $card = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => null,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/debit-cards/{$card->id}", ['is_active' => false]);

        $response->assertStatus(200);

        $card->refresh();
        $this->assertNotNull($card->disabled_at);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
        $card = DebitCard::factory()->create([
            'user_id' => $this->user->id,
        ]);

        //it should be 16 digit
        $response = $this->actingAs($this->user)->putJson("/api/debit-cards/{$card->id}", ['number' => '123']);

        $response->assertStatus(422);

    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
        $card = DebitCard::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/debit-cards/{$card->id}");

        $response->assertStatus(204);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
        $card = DebitCard::factory()->create([
            'user_id' => $this->user->id,
        ]);

        DebitCardTransaction::factory()->create([
            'debit_card_id' => $card->id,
            'amount' => 1000,
            'currency_code' => 'SGD'
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/debit-cards/{$card->id}");

        $response->assertStatus(403);
    }

    // Extra bonus for extra tests :)
}
