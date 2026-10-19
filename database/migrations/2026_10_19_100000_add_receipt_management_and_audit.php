<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('donation_receipts', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn('donation_receipts', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // A pending ToyyibPay bill has no verified donation date yet. Avoid
        // rebuilding or altering the column again when recovering a partial run.
        if (! $this->columnIsNullable('donation_receipts', 'donated_at')) {
            Schema::table('donation_receipts', function (Blueprint $table) {
                $table->date('donated_at')->nullable()->change();
            });
        }

        $this->ensureIndex(
            'donation_receipts',
            ['archived_at'],
            'donation_receipts_archived_at_index',
        );

        if (! Schema::hasTable('donation_receipt_audits')) {
            Schema::create('donation_receipt_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('donation_receipt_id')->nullable();
                $table->string('receipt_no');
                $table->string('action', 40);
                $table->foreignId('actor_id')->nullable();
                $table->string('actor_type', 40)->default('admin');
                $table->text('reason')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign(
                    'donation_receipt_id',
                    'donation_receipt_audits_donation_receipt_id_foreign',
                )->references('id')->on('donation_receipts')->nullOnDelete();
                $table->foreign(
                    'actor_id',
                    'donation_receipt_audits_actor_id_foreign',
                )->references('id')->on('users')->nullOnDelete();
                $table->index(
                    ['donation_receipt_id', 'action'],
                    'donation_receipt_audits_donation_receipt_id_action_index',
                );
            });
        }

        // Runtime audit lookups use the receipt relationship and action. There
        // is no runtime query by receipt_no, so deliberately do not recreate the
        // oversized (receipt_no, created_at) index that failed on production.
        $this->ensureIndex(
            'donation_receipt_audits',
            ['donation_receipt_id', 'action'],
            'donation_receipt_audits_donation_receipt_id_action_index',
        );

        $this->ensureForeignKey(
            'donation_receipt_audits',
            'donation_receipt_id',
            'donation_receipts',
            'donation_receipt_audits_donation_receipt_id_foreign',
        );
        $this->ensureForeignKey(
            'donation_receipt_audits',
            'actor_id',
            'users',
            'donation_receipt_audits_actor_id_foreign',
        );
    }

    public function down(): void
    {
        // Deliberately non-destructive. Receipt lifecycle and audit data are
        // financial records and must not be dropped by an accidental rollback.
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

    /**
     * @param  list<string>  $columns
     */
    private function ensureIndex(string $table, array $columns, string $name): void
    {
        if (Schema::hasIndex($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function ensureForeignKey(
        string $table,
        string $column,
        string $foreignTable,
        string $name,
    ): void {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (
                $foreignKey['columns'] === [$column]
                && strtolower($foreignKey['foreign_table']) === strtolower($foreignTable)
                && $foreignKey['foreign_columns'] === ['id']
            ) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $foreignTable, $name) {
            $blueprint->foreign($column, $name)
                ->references('id')
                ->on($foreignTable)
                ->nullOnDelete();
        });
    }
};
