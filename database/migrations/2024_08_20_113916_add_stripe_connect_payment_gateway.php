<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('facility_payment_gateway_settings', function (Blueprint $table) {
            $table->string('connected_account_id')->nullable();
        });

        DB::table('payment_gateways')->insert([
            'payment_gateway_id' => 9,
            'payment_gateway_name' => 'Stripe Connect',
            'is_active' => true,
        ]);

        DB::table('tags')->insert([
            'created_by_id' => 1,
            'updated_by_id' => 1,
            'name' => 'Stripe Connect',
            'type' => 'payment_processor',
        ]);

        Schema::table('regions', function (Blueprint $table) {
            $table->string('country_code_iso2', 2)->nullable();
        });

        DB::table('regions')->where('region_id', 1)->update(['country_code_iso2' => 'ZA']);
        DB::table('regions')->where('region_id', 2)->update(['country_code_iso2' => 'NA']);
        DB::table('regions')->where('region_id', 3)->update(['country_code_iso2' => 'AE']);
        DB::table('regions')->where('region_id', 4)->update(['country_code_iso2' => 'ZM']);
        DB::table('regions')->where('region_id', 5)->update(['country_code_iso2' => 'GB']);
        DB::table('regions')->where('region_id', 6)->update(['country_code_iso2' => 'HK']);
        DB::table('regions')->where('region_id', 7)->update(['country_code_iso2' => 'CN']);
        DB::table('regions')->where('region_id', 8)->update(['country_code_iso2' => 'CD']);
        DB::table('regions')->where('region_id', 9)->update(['country_code_iso2' => 'TH']);
        DB::table('regions')->where('region_id', 10)->update(['country_code_iso2' => 'TH']);
        DB::table('regions')->where('region_id', 12)->update(['country_code_iso2' => 'US']);
        DB::table('regions')->where('region_id', 13)->update(['country_code_iso2' => 'DE']);
        DB::table('regions')->where('region_id', 14)->update(['country_code_iso2' => 'IT']);
        DB::table('regions')->where('region_id', 15)->update(['country_code_iso2' => 'AT']);
        DB::table('regions')->where('region_id', 16)->update(['country_code_iso2' => 'US']);
        DB::table('regions')->where('region_id', 17)->update(['country_code_iso2' => 'BE']);
        DB::table('regions')->where('region_id', 18)->update(['country_code_iso2' => 'ZW']);
        DB::table('regions')->where('region_id', 19)->update(['country_code_iso2' => 'VN']);
        DB::table('regions')->where('region_id', 20)->update(['country_code_iso2' => 'IL']);
        DB::table('regions')->where('region_id', 21)->update(['country_code_iso2' => 'EG']);
        DB::table('regions')->where('region_id', 22)->update(['country_code_iso2' => 'CY']);
        DB::table('regions')->where('region_id', 23)->update(['country_code_iso2' => 'JP']);
        DB::table('regions')->where('region_id', 24)->update(['country_code_iso2' => 'NL']);
        DB::table('regions')->where('region_id', 25)->update(['country_code_iso2' => 'US']);
        DB::table('regions')->where('region_id', 26)->update(['country_code_iso2' => 'ES']);
        DB::table('regions')->where('region_id', 27)->update(['country_code_iso2' => 'SG']);
        DB::table('regions')->where('region_id', 29)->update(['country_code_iso2' => 'CH']);
        DB::table('regions')->where('region_id', 30)->update(['country_code_iso2' => 'AU']);
        DB::table('regions')->where('region_id', 31)->update(['country_code_iso2' => 'TW']);
        DB::table('regions')->where('region_id', 32)->update(['country_code_iso2' => 'GB']);
        DB::table('regions')->where('region_id', 33)->update(['country_code_iso2' => 'MZ']);
        DB::table('regions')->where('region_id', 34)->update(['country_code_iso2' => 'PT']);
        DB::table('regions')->where('region_id', 35)->update(['country_code_iso2' => 'CR']);
        DB::table('regions')->where('region_id', 36)->update(['country_code_iso2' => 'SE']);
        DB::table('regions')->where('region_id', 37)->update(['country_code_iso2' => 'MU']);
        DB::table('regions')->where('region_id', 38)->update(['country_code_iso2' => 'TN']);
        DB::table('regions')->where('region_id', 39)->update(['country_code_iso2' => 'BW']);
        DB::table('regions')->where('region_id', 40)->update(['country_code_iso2' => 'MU']);
        DB::table('regions')->where('region_id', 41)->update(['country_code_iso2' => 'GR']);
        DB::table('regions')->where('region_id', 43)->update(['country_code_iso2' => 'SA']);
        DB::table('regions')->where('region_id', 44)->update(['country_code_iso2' => 'KE']);
        DB::table('regions')->where('region_id', 45)->update(['country_code_iso2' => 'BH']);
        DB::table('regions')->where('region_id', 46)->update(['country_code_iso2' => 'SZ']);
        DB::table('regions')->where('region_id', 47)->update(['country_code_iso2' => 'AE']);
        DB::table('regions')->where('region_id', 48)->update(['country_code_iso2' => 'BR']);
        DB::table('regions')->where('region_id', 49)->update(['country_code_iso2' => 'PS']);
        DB::table('regions')->where('region_id', 50)->update(['country_code_iso2' => 'EG']);
        DB::table('regions')->where('region_id', 51)->update(['country_code_iso2' => 'EE']);
        DB::table('regions')->where('region_id', 52)->update(['country_code_iso2' => 'KW']);
        DB::table('regions')->where('region_id', 53)->update(['country_code_iso2' => 'IE']);
        DB::table('regions')->where('region_id', 54)->update(['country_code_iso2' => 'IT']);
        DB::table('regions')->where('region_id', 55)->update(['country_code_iso2' => 'SE']);
        DB::table('regions')->where('region_id', 56)->update(['country_code_iso2' => 'LS']);
        DB::table('regions')->where('region_id', 57)->update(['country_code_iso2' => 'RS']);
        DB::table('regions')->where('region_id', 58)->update(['country_code_iso2' => 'FR']);
        DB::table('regions')->where('region_id', 59)->update(['country_code_iso2' => 'MY']);
        DB::table('regions')->where('region_id', 60)->update(['country_code_iso2' => 'LU']);
        DB::table('regions')->where('region_id', 61)->update(['country_code_iso2' => 'BN']);
        DB::table('regions')->where('region_id', 62)->update(['country_code_iso2' => 'DK']);
        DB::table('regions')->where('region_id', 63)->update(['country_code_iso2' => 'JP']);
        DB::table('regions')->where('region_id', 64)->update(['country_code_iso2' => 'MG']);
        DB::table('regions')->where('region_id', 65)->update(['country_code_iso2' => 'HR']);
        DB::table('regions')->where('region_id', 66)->update(['country_code_iso2' => 'DZ']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facility_payment_gateway_settings', function (Blueprint $table) {
            $table->dropColumn('connected_account_id');
        });

        DB::table('payment_gateways')->where('payment_gateway_id', '=', 9)->delete();

        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn('country_code_iso2');
        });
    }
};
