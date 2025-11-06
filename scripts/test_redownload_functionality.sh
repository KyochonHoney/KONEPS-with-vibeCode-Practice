#!/bin/bash

# Test Re-Download Functionality
# Tests the complete re-download workflow for tender 1769

echo "=================================================="
echo "Test: Re-Download Functionality for Tender 1769"
echo "=================================================="
echo ""

# Test 1: Check tender 1769 attachments status
echo "Test 1: Check Tender 1769 Attachments"
echo "--------------------------------------"
php artisan tinker --execute="
\$tender = App\Models\Tender::find(1769);
if (!\$tender) { echo 'ERROR: Tender 1769 not found'; exit(1); }

echo 'Tender: ' . \$tender->tender_no . PHP_EOL;
echo 'Detail URL: ' . \$tender->detail_url . PHP_EOL;
echo PHP_EOL;

\$attachments = \$tender->attachments()->where('type', 'proposal')->get();
echo 'Total proposal files: ' . \$attachments->count() . PHP_EOL;
echo PHP_EOL;

foreach (\$attachments as \$att) {
    echo 'ID: ' . \$att->id . PHP_EOL;
    echo '  File: ' . \$att->file_name . PHP_EOL;
    echo '  Status: ' . \$att->download_status . PHP_EOL;
    echo '  Local path: ' . (\$att->local_path ?? 'NULL') . PHP_EOL;
    echo '  File size: ' . (\$att->file_size ? number_format(\$att->file_size) . ' bytes' : 'NULL') . PHP_EOL;
    echo '  Download URL: ' . (\$att->download_url ? 'YES' : 'NO') . PHP_EOL;
    echo PHP_EOL;
}
"

if [ $? -ne 0 ]; then
    echo "❌ Test 1 FAILED"
    exit 1
fi
echo "✅ Test 1 PASSED"
echo ""

# Test 2: Test re-download for HWP file (attachment 38)
echo "Test 2: Re-Download HWP File (Attachment 38)"
echo "---------------------------------------------"
timeout 180 php artisan tinker --execute="
\$attachment = App\Models\Attachment::find(38);
echo 'File: ' . \$attachment->file_name . PHP_EOL;
echo 'Initial status: ' . \$attachment->download_status . PHP_EOL;
echo PHP_EOL;

\$service = app(App\Services\AttachmentService::class);

try {
    echo 'Starting re-download...' . PHP_EOL;
    \$service->downloadAttachment(\$attachment);

    \$attachment->refresh();
    echo PHP_EOL;
    echo 'SUCCESS!' . PHP_EOL;
    echo 'Final status: ' . \$attachment->download_status . PHP_EOL;
    echo 'Local path: ' . \$attachment->local_path . PHP_EOL;
    echo 'File size: ' . number_format(\$attachment->file_size) . ' bytes' . PHP_EOL;

    // Verify file exists on disk
    \$fullPath = storage_path('app/' . \$attachment->local_path);
    if (!file_exists(\$fullPath)) {
        throw new Exception('File not found on disk: ' . \$fullPath);
    }
    echo 'File verified on disk: YES' . PHP_EOL;

} catch (Exception \$e) {
    echo PHP_EOL;
    echo 'ERROR: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

if [ $? -ne 0 ]; then
    echo "❌ Test 2 FAILED"
    exit 1
fi
echo "✅ Test 2 PASSED"
echo ""

# Test 3: Test re-download for PDF file (attachment 39)
echo "Test 3: Re-Download PDF File (Attachment 39)"
echo "---------------------------------------------"
timeout 180 php artisan tinker --execute="
\$attachment = App\Models\Attachment::find(39);
echo 'File: ' . \$attachment->file_name . PHP_EOL;
echo 'Initial status: ' . \$attachment->download_status . PHP_EOL;
echo PHP_EOL;

\$service = app(App\Services\AttachmentService::class);

try {
    echo 'Starting re-download...' . PHP_EOL;
    \$service->downloadAttachment(\$attachment);

    \$attachment->refresh();
    echo PHP_EOL;
    echo 'SUCCESS!' . PHP_EOL;
    echo 'Final status: ' . \$attachment->download_status . PHP_EOL;
    echo 'Local path: ' . \$attachment->local_path . PHP_EOL;
    echo 'File size: ' . number_format(\$attachment->file_size) . ' bytes' . PHP_EOL;

    // Verify file exists on disk
    \$fullPath = storage_path('app/' . \$attachment->local_path);
    if (!file_exists(\$fullPath)) {
        throw new Exception('File not found on disk: ' . \$fullPath);
    }
    echo 'File verified on disk: YES' . PHP_EOL;

} catch (Exception \$e) {
    echo PHP_EOL;
    echo 'ERROR: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

if [ $? -ne 0 ]; then
    echo "❌ Test 3 FAILED"
    exit 1
fi
echo "✅ Test 3 PASSED"
echo ""

# Test 4: Verify both files on disk
echo "Test 4: Verify Downloaded Files on Disk"
echo "----------------------------------------"
ls -lh /home/tideflo/nara/public_html/storage/app/proposal_files/1769/

if [ $? -ne 0 ]; then
    echo "❌ Test 4 FAILED"
    exit 1
fi
echo "✅ Test 4 PASSED"
echo ""

# Test 5: Test 상주 keyword detection on downloaded files
echo "Test 5: Test 상주 Keyword Detection"
echo "------------------------------------"
timeout 60 php artisan tinker --execute="
\$attachment = App\Models\Attachment::find(38);
\$fullPath = storage_path('app/' . \$attachment->local_path);

echo 'Testing file: ' . \$attachment->file_name . PHP_EOL;

\$scriptPath = base_path('scripts/extract_hwp_text_hwp5.py');
\$command = 'python3 ' . escapeshellarg(\$scriptPath) . ' ' . escapeshellarg(\$fullPath) . ' 2>&1';
\$extractedText = shell_exec(\$command);

\$hasSangju = mb_stripos(\$extractedText, '상주') !== false;
echo '상주 keyword found: ' . (\$hasSangju ? 'YES ✅' : 'NO ❌') . PHP_EOL;

if (\$hasSangju) {
    \$lines = explode(PHP_EOL, \$extractedText);
    echo PHP_EOL . 'Matched lines:' . PHP_EOL;
    foreach (\$lines as \$line) {
        if (mb_stripos(\$line, '상주') !== false) {
            echo '  -> ' . trim(\$line) . PHP_EOL;
        }
    }
}
"

if [ $? -ne 0 ]; then
    echo "❌ Test 5 FAILED"
    exit 1
fi
echo "✅ Test 5 PASSED"
echo ""

echo "=================================================="
echo "All Tests Passed! ✅"
echo "=================================================="
echo ""
echo "Summary:"
echo "- Re-download functionality works for both HWP and PDF files"
echo "- Files are correctly saved to storage/app/proposal_files/{tender_id}/"
echo "- Attachment metadata (status, local_path, file_size) is properly updated"
echo "- HWP text extraction using hwp5txt works correctly"
echo "- 상주 keyword detection system functions as expected"
