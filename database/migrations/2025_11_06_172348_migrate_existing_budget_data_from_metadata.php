<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 기존 데이터를 metadata에서 가져와서 업데이트
        DB::table('tenders')->whereNotNull('metadata')->chunkById(100, function ($tenders) {
            foreach ($tenders as $tender) {
                // metadata가 이중 인코딩되어 있으므로 두 번 디코딩
                $metadata = json_decode(json_decode($tender->metadata), true);

                if (empty($metadata) || !is_array($metadata)) continue;

                $updates = [];

                // total_budget: asignBdgtAmt (사업금액)
                if (isset($metadata['asignBdgtAmt']) && !empty($metadata['asignBdgtAmt']) && !is_array($metadata['asignBdgtAmt'])) {
                    $updates['total_budget'] = $metadata['asignBdgtAmt'];
                }

                // allocated_budget: presmptPrce (추정가격)
                if (isset($metadata['presmptPrce']) && !empty($metadata['presmptPrce']) && !is_array($metadata['presmptPrce'])) {
                    $updates['allocated_budget'] = $metadata['presmptPrce'];
                }

                // vat: VAT (부가세)
                if (isset($metadata['VAT']) && !empty($metadata['VAT']) && !is_array($metadata['VAT'])) {
                    $updates['vat'] = $metadata['VAT'];
                }

                // 부가세가 없으면 계산
                if (empty($updates['vat']) && !empty($updates['total_budget']) && !empty($updates['allocated_budget'])) {
                    $updates['vat'] = $updates['total_budget'] - $updates['allocated_budget'];
                }

                if (!empty($updates)) {
                    DB::table('tenders')->where('id', $tender->id)->update($updates);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 데이터 복원은 불가능하므로 아무 작업도 하지 않음
        // 필요시 백업에서 복원
    }
};
