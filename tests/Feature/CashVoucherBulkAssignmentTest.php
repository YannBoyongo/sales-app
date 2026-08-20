<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AccountingTransaction;
use App\Models\Branch;
use App\Models\CashVoucher;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashVoucherBulkAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reassign_multiple_approved_unaccounted_vouchers(): void
    {
        $admin = $this->admin();
        [$sourceBranch] = $this->branchAndTerminal('Source');
        [$targetBranch, $targetTerminal] = $this->branchAndTerminal('Target');
        $first = $this->voucher($sourceBranch, 'BC-001');
        $second = $this->voucher($sourceBranch, 'BC-002');

        $response = $this->actingAs($admin)->patchJson(route('cash-vouchers.bulk-assignment'), [
            'voucher_ids' => [$first->id, $second->id],
            'branch_id' => $targetBranch->id,
            'pos_terminal_id' => $targetTerminal->id,
        ]);

        $response->assertOk()->assertJson(['updated_count' => 2]);
        $this->assertDatabaseHas('cash_vouchers', [
            'id' => $first->id,
            'branch_id' => $targetBranch->id,
            'pos_terminal_id' => $targetTerminal->id,
        ]);
        $this->assertDatabaseHas('cash_vouchers', [
            'id' => $second->id,
            'branch_id' => $targetBranch->id,
            'pos_terminal_id' => $targetTerminal->id,
        ]);
    }

    public function test_terminal_must_belong_to_selected_branch(): void
    {
        $admin = $this->admin();
        [$sourceBranch, $sourceTerminal] = $this->branchAndTerminal('Source');
        [$targetBranch] = $this->branchAndTerminal('Target');
        $voucher = $this->voucher($sourceBranch, 'BC-001');

        $response = $this->actingAs($admin)->patchJson(route('cash-vouchers.bulk-assignment'), [
            'voucher_ids' => [$voucher->id],
            'branch_id' => $targetBranch->id,
            'pos_terminal_id' => $sourceTerminal->id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('pos_terminal_id');
        $this->assertDatabaseHas('cash_vouchers', [
            'id' => $voucher->id,
            'branch_id' => $sourceBranch->id,
            'pos_terminal_id' => null,
        ]);
    }

    public function test_accounted_voucher_rejects_the_entire_batch(): void
    {
        $admin = $this->admin();
        [$sourceBranch] = $this->branchAndTerminal('Source');
        [$targetBranch, $targetTerminal] = $this->branchAndTerminal('Target');
        $eligible = $this->voucher($sourceBranch, 'BC-001');
        $accounted = $this->voucher($sourceBranch, 'BC-002');
        $transaction = AccountingTransaction::query()->create([
            'user_id' => $admin->id,
            'transaction_date' => now()->toDateString(),
            'reference' => 'Test accounting entry',
            'amount' => '10.00',
            'entry_type' => 'debit',
            'account_code' => '5700',
        ]);
        $accounted->update(['accounting_transaction_id' => $transaction->id]);

        $response = $this->actingAs($admin)->patchJson(route('cash-vouchers.bulk-assignment'), [
            'voucher_ids' => [$eligible->id, $accounted->id],
            'branch_id' => $targetBranch->id,
            'pos_terminal_id' => $targetTerminal->id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('voucher_ids');
        $this->assertSame($sourceBranch->id, $eligible->fresh()->branch_id);
        $this->assertSame($sourceBranch->id, $accounted->fresh()->branch_id);
    }

    public function test_duplicate_voucher_number_in_target_branch_rejects_the_batch(): void
    {
        $admin = $this->admin();
        [$sourceBranch] = $this->branchAndTerminal('Source');
        [$targetBranch, $targetTerminal] = $this->branchAndTerminal('Target');
        $sourceVoucher = $this->voucher($sourceBranch, 'BC-SAME');
        $this->voucher($targetBranch, 'BC-SAME');

        $response = $this->actingAs($admin)->patchJson(route('cash-vouchers.bulk-assignment'), [
            'voucher_ids' => [$sourceVoucher->id],
            'branch_id' => $targetBranch->id,
            'pos_terminal_id' => $targetTerminal->id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('voucher_ids');
        $this->assertSame($sourceBranch->id, $sourceVoucher->fresh()->branch_id);
    }

    public function test_non_admin_cannot_use_bulk_reassignment(): void
    {
        $user = User::factory()->create();
        [$branch, $terminal] = $this->branchAndTerminal('Branch');
        $voucher = $this->voucher($branch, 'BC-001');

        $this->actingAs($user)->patchJson(route('cash-vouchers.bulk-assignment'), [
            'voucher_ids' => [$voucher->id],
            'branch_id' => $branch->id,
            'pos_terminal_id' => $terminal->id,
        ])->assertForbidden();
    }

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => UserRole::Admin->value],
            ['name' => 'Administrateur'],
        );
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /** @return array{Branch, PosTerminal} */
    private function branchAndTerminal(string $name): array
    {
        $branch = Branch::query()->create(['name' => $name]);
        $location = Location::query()->create([
            'branch_id' => $branch->id,
            'name' => $name.' POS',
            'kind' => Location::KIND_POINT_OF_SALE,
        ]);
        $terminal = PosTerminal::query()->create([
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'name' => $name.' Terminal',
        ]);

        return [$branch, $terminal];
    }

    private function voucher(Branch $branch, string $number): CashVoucher
    {
        return CashVoucher::query()->create([
            'branch_id' => $branch->id,
            'voucher_no' => $number,
            'date' => now()->toDateString(),
            'description' => 'Test voucher',
            'type' => 'entry',
            'amount' => '10.00',
            'approved_at' => now(),
        ]);
    }
}
