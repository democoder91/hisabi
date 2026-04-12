<?php

namespace Tests\Unit\Models\Brands;

use App\Domains\Account\Models\Account;
use App\Domains\Brand\Models\Brand;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_name()
    {
        $sut = Brand::factory()->create(['name' => ['en' => 'test']]);

        $this->assertEquals("test", $sut->name);
    }

    public function test_it_belongs_to_category()
    {
        $sut = Brand::factory()
                    ->forCategory(['name' => ['en' => 'categoryTest']])
                    ->create();

        $this->assertEquals('categoryTest', $sut->category->name);
    }

    public function test_it_has_transactions()
    {
        $sut = Brand::factory()->create();

        Transaction::factory()->create([
            'account_id' => Account::factory()->create(['user_id' => $sut->user_id])->id,
            'brand_id' => $sut->id,
            'amount' => 3,
        ]);

        $this->assertCount(1, $sut->transactions);
    }

    public function test_it_does_search_about_amount_brand_or_note()
    {
        Brand::factory()->forCategory(['name' => ['en' => 'internet']])->create(['name' => ['en' => 'google']]);
        Brand::factory()->forCategory(['name' => ['en' => 'shopping']])->create(['name' => ['en' => 'ikea']]);
        Brand::factory()->forCategory(['name' => ['en' => 'shopping']])->create(['name' => ['en' => 'lulu']]);

        $this->assertCount(1, Brand::search('goo')->get());
        $this->assertCount(1, Brand::search('internet')->get());
        $this->assertCount(2, Brand::search('shopping')->get());
        $this->assertCount(0, Brand::search('other')->get());
    }
}
