<?php

namespace Tests\Feature;

use App\Models\SharafPayment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SharafPaymentMethodTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sharaf_payments');
        Schema::dropIfExists('payment_definitions');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('user_sharaf_type');
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('sharaf_types');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('its_no')->nullable();
            $table->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('user_role', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
        });

        Schema::create('sharaf_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('user_sharaf_type', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('sharaf_type_id');
        });

        Schema::create('sharaf_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('payment_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('sharafs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->integer('rank')->default(0);
            $table->string('name')->nullable();
            $table->integer('capacity')->default(1);
            $table->string('status')->default('pending');
            $table->string('hof_its');
            $table->timestamps();
        });

        Schema::create('sharaf_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->unsignedBigInteger('payment_definition_id');
            $table->decimal('payment_amount', 10, 2)->default(0);
            $table->string('payment_currency', 3)->nullable();
            $table->boolean('payment_status')->default(false);
            $table->decimal('paid_amount', 10, 2)->nullable();
            $table->string('paid_currency', 3)->nullable();
            $table->string('payment_method', 16)->nullable();
            $table->string('payment_city', 120)->nullable();
            $table->string('receipt_path', 500)->nullable();
            $table->timestamps();
        });

        DB::table('users')->insert([
            'name' => 'Finance',
            'email' => 'finance@example.com',
            'its_no' => '30361286',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sharaf_definitions')->insert([
            'id' => 1,
            'name' => 'Najwa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_definitions')->insert([
            'id' => 7,
            'sharaf_definition_id' => 1,
            'name' => 'Najwa Raqam',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sharafs')->insert([
            'id' => 4,
            'sharaf_definition_id' => 1,
            'hof_its' => '30361286',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::fake('local');
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        // APP_URL includes /api. After the first request Laravel adopts the host root and drops that prefix.
        URL::forceRootUrl(config('app.url'));

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    public function test_cash_payment_records_method_without_a_receipt(): void
    {
        $response = $this->patchJson('/sharafs/4/payments/7', [
            'paid' => true,
            'paid_amount' => 2500,
            'paid_currency' => 'lkr',
            'payment_method' => 'cash',
            'payment_city' => 'Colombo',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.payment_city', 'Colombo')
            ->assertJsonPath('data.paid_currency', 'LKR')
            ->assertJsonPath('data.has_receipt', false);

        $this->assertArrayNotHasKey('receipt_path', $response->json('data'));

        $payment = SharafPayment::first();
        $this->assertSame('cash', $payment->payment_method);
        $this->assertSame('Colombo', $payment->payment_city);
        $this->assertNull($payment->receipt_path);
    }

    public function test_transfer_can_store_a_pdf_receipt(): void
    {
        $response = $this->post('/sharafs/4/payments/7', [
            'paid' => '1',
            'paid_amount' => '1800.50',
            'paid_currency' => 'LKR',
            'payment_method' => 'transfer',
            'payment_city' => 'Kandy',
            'receipt' => UploadedFile::fake()->create('slip.pdf', 40, 'application/pdf'),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_method', 'transfer')
            ->assertJsonPath('data.has_receipt', true);

        $payment = SharafPayment::first();
        Storage::disk('local')->assertExists($payment->receipt_path);

        $download = $this->withCredentials()
            ->withUnencryptedCookie('user', '30361286')
            ->get('/sharafs/4/payments/7/receipt');

        $download->assertOk();
        $this->assertSame('application/pdf', $download->headers->get('content-type'));
    }

    public function test_marking_unpaid_removes_the_receipt(): void
    {
        $this->post('/sharafs/4/payments/7', [
            'paid' => '1',
            'paid_amount' => '10',
            'paid_currency' => 'LKR',
            'payment_method' => 'transfer',
            'payment_city' => 'Galle',
            'receipt' => UploadedFile::fake()->image('slip.jpg'),
        ])->assertOk();

        $path = SharafPayment::first()->receipt_path;
        $this->assertNotNull($path);

        $this->patchJson('/sharafs/4/payments/7', [
            'paid' => false,
        ])->assertOk()
            ->assertJsonPath('data.payment_status', false)
            ->assertJsonPath('data.payment_method', null)
            ->assertJsonPath('data.payment_city', null)
            ->assertJsonPath('data.has_receipt', false);

        Storage::disk('local')->assertMissing($path);
    }

    public function test_switching_to_cash_deletes_an_existing_receipt(): void
    {
        $this->post('/sharafs/4/payments/7', [
            'paid' => '1',
            'payment_method' => 'transfer',
            'payment_city' => 'Colombo',
            'receipt' => UploadedFile::fake()->create('slip.pdf', 20, 'application/pdf'),
        ])->assertOk();

        $path = SharafPayment::first()->receipt_path;

        $this->patchJson('/sharafs/4/payments/7', [
            'paid' => true,
            'paid_amount' => 10,
            'paid_currency' => 'LKR',
            'payment_method' => 'cash',
            'payment_city' => 'Colombo',
        ])->assertOk()
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.has_receipt', false);

        Storage::disk('local')->assertMissing($path);
    }

    public function test_receipt_requires_a_signed_in_user(): void
    {
        $this->post('/sharafs/4/payments/7', [
            'paid' => '1',
            'payment_method' => 'transfer',
            'payment_city' => 'Colombo',
            'receipt' => UploadedFile::fake()->image('slip.png'),
        ])->assertOk();

        $this->getJson('/sharafs/4/payments/7/receipt')
            ->assertStatus(401);
    }

    public function test_rejects_an_invalid_method_and_file_type(): void
    {
        $this->patchJson('/sharafs/4/payments/7', [
            'paid' => true,
            'payment_method' => 'cheque',
        ])->assertStatus(422);

        $this->post('/sharafs/4/payments/7', [
            'paid' => '1',
            'payment_method' => 'transfer',
            'payment_city' => 'Colombo',
            'receipt' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertStatus(422);
    }

    public function test_recording_a_payment_requires_method_and_city(): void
    {
        $this->patchJson('/sharafs/4/payments/7', [
            'paid' => true,
            'paid_amount' => 100,
            'payment_city' => 'Colombo',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Payment method is required.');

        $this->patchJson('/sharafs/4/payments/7', [
            'paid' => true,
            'paid_amount' => 100,
            'payment_method' => 'cash',
            'payment_city' => '   ',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Payment city is required.');
    }
}
