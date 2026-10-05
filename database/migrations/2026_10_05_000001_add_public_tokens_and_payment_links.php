<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Unguessable tokens used by the QR codes printed on reports and invoices.
        foreach (['test_reports', 'patients'] as $tableName) {
            if (!Schema::hasColumn($tableName, 'public_token')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('public_token', 64)->nullable()->unique();
                });
            }

            DB::table($tableName)->whereNull('public_token')->orderBy('id')->select('id')
                ->chunkById(500, function ($rows) use ($tableName) {
                    foreach ($rows as $row) {
                        DB::table($tableName)->where('id', $row->id)->update(['public_token' => Str::random(40)]);
                    }
                });
        }

        // Link every booked test to the bill it was charged on, so editing one visit
        // can no longer overwrite the totals of another.
        if (!Schema::hasColumn('appointments', 'payment_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->foreignId('payment_id')->nullable()->after('patient_id')
                    ->constrained('payments')->nullOnDelete();
            });
        }

        // Back-fill: patients that only ever had one bill own all of their appointments on it.
        $singleBillPatients = DB::table('payments')
            ->select('patient_id', DB::raw('MIN(id) as payment_id'))
            ->groupBy('patient_id')
            ->havingRaw('COUNT(*) = 1')
            ->get();

        foreach ($singleBillPatients as $row) {
            DB::table('appointments')
                ->where('patient_id', $row->patient_id)
                ->whereNull('payment_id')
                ->update(['payment_id' => $row->payment_id]);
        }

        // Result rows may legitimately be saved before a value is entered.
        Schema::table('test_report_items', function (Blueprint $table) {
            $table->string('observed_value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('test_report_items', function (Blueprint $table) {
            $table->string('observed_value')->nullable(false)->default('')->change();
        });

        if (Schema::hasColumn('appointments', 'payment_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_id');
            });
        }

        foreach (['test_reports', 'patients'] as $tableName) {
            if (Schema::hasColumn($tableName, 'public_token')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropUnique(['public_token']);
                    $table->dropColumn('public_token');
                });
            }
        }
    }
};
