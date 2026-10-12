<?php

namespace Tests\Feature;

use App\Models\PaymentDefinition;
use App\Models\SharafPayment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentDefinitionDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sharaf_payments');
        Schema::dropIfExists('payment_definitions');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('audit_logs');

        Schema::create('sharafs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('payment_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->unsignedBigInteger('payment_definition_id');
            $table->decimal('payment_amount', 10, 2)->default(0);
            $table->boolean('payment_status')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 64);
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 32);
            $table->string('actor_its')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('summary')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('parent_entity', 64)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function test_delete_removes_recorded_payments(): void
    {
        $definition = PaymentDefinition::create([
            'sharaf_definition_id' => 1,
            'name' => 'lagat',
        ]);
        $sharafId = DB::table('sharafs')->insertGetId([
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        SharafPayment::create([
            'sharaf_id' => $sharafId,
            'payment_definition_id' => $definition->id,
            'payment_amount' => 1500,
            'payment_status' => true,
        ]);

        $response = $this->deleteJson("/payment-definitions/{$definition->id}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertNull(PaymentDefinition::find($definition->id));
        $this->assertSame(0, SharafPayment::query()->where('payment_definition_id', $definition->id)->count());
    }
}
