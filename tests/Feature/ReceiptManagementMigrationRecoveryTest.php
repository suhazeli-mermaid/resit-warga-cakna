<?php

namespace Tests\Feature;

use App\Models\DonationReceipt;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReceiptManagementMigrationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_install_has_only_production_compatible_required_indexes_and_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasColumn('donation_receipts', 'archived_at'));
        $this->assertTrue(Schema::hasColumn('donation_receipts', 'deleted_at'));
        $this->assertTrue($this->columnIsNullable('donation_receipts', 'donated_at'));
        $this->assertTrue(Schema::hasIndex('donation_receipts', ['archived_at']));
        $this->assertTrue(Schema::hasTable('donation_receipt_audits'));

        $this->assertTrue(Schema::hasIndex(
            'donation_receipt_audits',
            ['donation_receipt_id', 'action'],
        ));
        $this->assertFalse(Schema::hasIndex(
            'donation_receipt_audits',
            ['receipt_no', 'created_at'],
        ));
        $this->assertFalse($this->anyIndexContainsColumn(
            'donation_receipt_audits',
            'receipt_no',
        ));

        $this->assertTrue($this->foreignKeyExists(
            'donation_receipt_audits',
            'donation_receipt_id',
            'donation_receipts',
        ));
        $this->assertTrue($this->foreignKeyExists(
            'donation_receipt_audits',
            'actor_id',
            'users',
        ));
    }

    public function test_partial_migration_recovers_missing_keys_without_recreating_tables_or_losing_data(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $receipt = $this->receipt();
        $receiptCount = DB::table('donation_receipts')->count();
        $donatedAt = DB::table('donation_receipts')->where('id', $receipt->id)->value('donated_at');

        Schema::drop('donation_receipt_audits');
        Schema::create('donation_receipt_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_receipt_id')->nullable();
            $table->string('receipt_no');
            $table->string('action', 40);
            $table->foreignId('actor_id')->nullable();
            $table->string('actor_type', 40)->default('admin');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $longReceiptNumber = str_repeat('R', 255);
        DB::table('donation_receipt_audits')->insert([
            'donation_receipt_id' => $receipt->id,
            'receipt_no' => $longReceiptNumber,
            'action' => 'delete',
            'actor_id' => $actor->id,
            'actor_type' => 'admin',
            'reason' => 'Existing production audit data',
            'created_at' => '2026-10-19 12:00:00',
        ]);

        $this->migration()->up();

        $this->assertSame($receiptCount, DB::table('donation_receipts')->count());
        $this->assertSame($donatedAt, DB::table('donation_receipts')->where('id', $receipt->id)->value('donated_at'));
        $this->assertSame(1, DB::table('donation_receipt_audits')->count());
        $this->assertSame(
            $longReceiptNumber,
            DB::table('donation_receipt_audits')->value('receipt_no'),
        );
        $this->assertSame(
            'Existing production audit data',
            DB::table('donation_receipt_audits')->value('reason'),
        );
        $this->assertTrue(Schema::hasIndex(
            'donation_receipt_audits',
            ['donation_receipt_id', 'action'],
        ));
        $this->assertFalse($this->anyIndexContainsColumn(
            'donation_receipt_audits',
            'receipt_no',
        ));
        $this->assertTrue($this->foreignKeyExists(
            'donation_receipt_audits',
            'donation_receipt_id',
            'donation_receipts',
        ));
        $this->assertTrue($this->foreignKeyExists(
            'donation_receipt_audits',
            'actor_id',
            'users',
        ));
    }

    public function test_repeated_execution_is_idempotent_and_preserves_existing_rows(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $receipt = $this->receipt();

        DB::table('donation_receipt_audits')->insert([
            'donation_receipt_id' => $receipt->id,
            'receipt_no' => $receipt->receipt_no,
            'action' => 'archive',
            'actor_id' => $actor->id,
            'actor_type' => 'admin',
            'reason' => 'Must survive repeated recovery',
            'created_at' => '2026-10-19 13:00:00',
        ]);

        $this->migration()->up();
        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(1, DB::table('donation_receipt_audits')->count());
        $this->assertDatabaseHas('donation_receipt_audits', [
            'donation_receipt_id' => $receipt->id,
            'receipt_no' => $receipt->receipt_no,
            'reason' => 'Must survive repeated recovery',
        ]);
        $this->assertSame(1, DB::table('donation_receipts')->where('id', $receipt->id)->count());
        $this->assertTrue(Schema::hasColumn('donation_receipts', 'archived_at'));
        $this->assertTrue(Schema::hasColumn('donation_receipts', 'deleted_at'));
        $this->assertTrue(Schema::hasIndex(
            'donation_receipt_audits',
            ['donation_receipt_id', 'action'],
        ));
        $this->assertFalse($this->anyIndexContainsColumn(
            'donation_receipt_audits',
            'receipt_no',
        ));
    }

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_10_19_100000_add_receipt_management_and_audit.php',
        );
    }

    private function receipt(): DonationReceipt
    {
        return DonationReceipt::create([
            'receipt_no' => 'REC-RECOVERY-'.strtoupper(substr(uniqid(), -6)),
            'donor_name' => 'Migration Recovery Donor',
            'amount' => 50,
            'payment_method' => 'bank_transfer',
            'status' => DonationReceipt::STATUS_PENDING,
            'donated_at' => '2026-07-16',
        ]);
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        foreach (Schema::getColumns($table) as $definition) {
            if (strtolower($definition['name']) === strtolower($column)) {
                return (bool) $definition['nullable'];
            }
        }

        return false;
    }

    private function foreignKeyExists(string $table, string $column, string $foreignTable): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (
                $foreignKey['columns'] === [$column]
                && strtolower($foreignKey['foreign_table']) === strtolower($foreignTable)
                && $foreignKey['foreign_columns'] === ['id']
            ) {
                return true;
            }
        }

        return false;
    }

    private function anyIndexContainsColumn(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (in_array($column, $index['columns'], true)) {
                return true;
            }
        }

        return false;
    }
}
