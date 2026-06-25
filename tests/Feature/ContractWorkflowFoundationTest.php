<?php

namespace Tests\Feature;

use App\Models\BusinessContract;
use App\Models\ContractApplication;
use App\Models\ContractProofSubmission;
use App\Models\MarketerSocialLink;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractWorkflowFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_owner_can_update_open_contract_and_adjust_escrow_upward(): void
    {
        $business = $this->makeBusinessUser(balance: 200);

        $contract = BusinessContract::create([
            'user_id' => $business->id,
            'title' => 'Creator push',
            'platform' => 'tiktok',
            'description' => 'Drive engagement on the new launch.',
            'budget' => 20,
            'slots' => 2,
            'funded_amount' => 44,
            'deadline_at' => now()->addDays(5)->toDateString(),
            'status' => BusinessContract::STATUS_OPEN,
        ]);

        $response = $this
            ->from(route('contracts.index'))
            ->actingAs($business)
            ->put(route('contracts.update', $contract), [
                'title' => 'Creator push',
                'platform' => 'tiktok',
                'description' => 'Drive engagement on the new launch.',
                'budget' => 30,
                'slots' => 2,
                'deadline_at' => now()->addDays(7)->toDateString(),
            ]);

        $response
            ->assertRedirect(route('contracts.index'))
            ->assertSessionHas('success', 'Contract updated successfully.');

        $contract->refresh();
        $business->refresh();

        $this->assertSame(30.0, (float) $contract->budget);
        $this->assertSame(66.0, (float) $contract->funded_amount);
        $this->assertSame(178.0, (float) $business->balance);

        $adjustment = Transaction::where('user_id', $business->id)
            ->where('type', 'contract_payout')
            ->latest('id')
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertSame(-22.0, (float) $adjustment->amount);
        $this->assertStringContainsString('Escrow increase for contract #'.$contract->id, (string) $adjustment->notes);
    }

    public function test_business_owner_cannot_change_budget_or_slots_after_marketer_is_hired(): void
    {
        $business = $this->makeBusinessUser(balance: 200);
        $marketer = $this->makeApprovedMarketer();

        $contract = BusinessContract::create([
            'user_id' => $business->id,
            'title' => 'Launch blitz',
            'platform' => 'instagram',
            'description' => 'Boost the campaign rollout.',
            'budget' => 25,
            'slots' => 2,
            'funded_amount' => 55,
            'deadline_at' => now()->addDays(4)->toDateString(),
            'status' => BusinessContract::STATUS_OPEN,
        ]);

        ContractApplication::create([
            'business_contract_id' => $contract->id,
            'marketer_id' => $marketer->id,
            'pitch' => 'I can handle this rollout.',
            'status' => ContractApplication::STATUS_APPROVED,
        ]);

        $response = $this
            ->from(route('contracts.index'))
            ->actingAs($business)
            ->put(route('contracts.update', $contract), [
                'title' => 'Launch blitz',
                'platform' => 'instagram',
                'description' => 'Boost the campaign rollout.',
                'budget' => 40,
                'slots' => 2,
                'deadline_at' => now()->addDays(6)->toDateString(),
            ]);

        $response
            ->assertRedirect(route('contracts.index'))
            ->assertSessionHas('error', 'Budget and slot changes are locked once a marketer has been hired. You can still update the brief, platform, or deadline.');

        $contract->refresh();
        $business->refresh();

        $this->assertSame(25.0, (float) $contract->budget);
        $this->assertSame(55.0, (float) $contract->funded_amount);
        $this->assertSame(200.0, (float) $business->balance);
    }

    public function test_proof_approval_completes_application_and_pays_marketer(): void
    {
        $business = $this->makeBusinessUser();
        $marketer = $this->makeApprovedMarketer(balance: 10, notificationPrefs: ['email' => false, 'whatsapp' => false]);

        $contract = BusinessContract::create([
            'user_id' => $business->id,
            'title' => 'Story campaign',
            'platform' => 'facebook',
            'description' => 'Publish and report story performance.',
            'budget' => 50,
            'slots' => 1,
            'funded_amount' => 55,
            'deadline_at' => now()->addDays(3)->toDateString(),
            'status' => BusinessContract::STATUS_FILLED,
        ]);

        $application = ContractApplication::create([
            'business_contract_id' => $contract->id,
            'marketer_id' => $marketer->id,
            'pitch' => 'Ready to deliver.',
            'status' => ContractApplication::STATUS_APPROVED,
        ]);

        $proof = ContractProofSubmission::create([
            'contract_application_id' => $application->id,
            'marketer_id' => $marketer->id,
            'proof_url' => 'https://example.com/proof/story-campaign',
            'notes' => 'Initial proof submission.',
            'status' => 'pending',
        ]);

        $response = $this
            ->from(route('contracts.show', $contract))
            ->actingAs($business)
            ->post(route('proof.review', $proof), [
                'decision' => 'approved',
            ]);

        $response
            ->assertRedirect(route('contracts.show', $contract))
            ->assertSessionHas('success', 'Proof approved — funds released to marketer.');

        $proof->refresh();
        $application->refresh();
        $marketer->refresh();

        $this->assertSame('approved', $proof->status);
        $this->assertSame(ContractApplication::STATUS_COMPLETED, $application->status);
        $this->assertSame(60.0, (float) $marketer->balance);

        $payout = Transaction::where('user_id', $marketer->id)
            ->where('type', 'contract_earning')
            ->latest('id')
            ->first();

        $this->assertNotNull($payout);
        $this->assertSame(50.0, (float) $payout->amount);
    }

    public function test_marketer_cannot_reapply_over_an_approved_contract_slot(): void
    {
        $business = $this->makeBusinessUser();
        $marketer = $this->makeApprovedMarketer();

        $contract = BusinessContract::create([
            'user_id' => $business->id,
            'title' => 'Community boost',
            'platform' => 'telegram',
            'description' => 'Promote the community growth sprint.',
            'budget' => 15,
            'slots' => 1,
            'funded_amount' => 16.5,
            'deadline_at' => now()->addDays(2)->toDateString(),
            'status' => BusinessContract::STATUS_OPEN,
        ]);

        MarketerSocialLink::create([
            'user_id' => $marketer->id,
            'platform' => 'telegram',
            'handle' => '@zim_marketer',
            'profile_url' => 'https://t.me/zim_marketer',
            'follower_count' => 1200,
            'verified' => false,
        ]);

        $application = ContractApplication::create([
            'business_contract_id' => $contract->id,
            'marketer_id' => $marketer->id,
            'pitch' => 'Existing winning pitch.',
            'status' => ContractApplication::STATUS_APPROVED,
        ]);

        $response = $this
            ->from(route('contracts.index'))
            ->actingAs($marketer)
            ->post(route('contracts.apply', $contract), [
                'pitch' => 'Trying to overwrite the approved slot.',
            ]);

        $response
            ->assertRedirect(route('contracts.index'))
            ->assertSessionHas('error', 'You already hold a live slot on this contract.');

        $application->refresh();

        $this->assertSame(ContractApplication::STATUS_APPROVED, $application->status);
        $this->assertSame('Existing winning pitch.', $application->pitch);
    }

    private function makeBusinessUser(float $balance = 0): User
    {
        return User::factory()->create([
            'account_type' => 'business',
            'balance' => $balance,
            'notification_prefs' => ['email' => false, 'whatsapp' => false],
        ]);
    }

    private function makeApprovedMarketer(float $balance = 0, ?array $notificationPrefs = null): User
    {
        return User::factory()->create([
            'role' => 'marketer',
            'marketer_status' => 'approved',
            'balance' => $balance,
            'notification_prefs' => $notificationPrefs ?? ['email' => false, 'whatsapp' => false],
        ]);
    }
}
