<?php

namespace Tests\Unit\Sms;

use App\Services\Sms\MelipayamakCodes;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SpreadsheetReader;
use App\Services\Sms\TemplateBody;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class SmsHelpersTest extends TestCase
{
    public static function phones(): array
    {
        return [
            ['09121234567', '09121234567'],
            ['۰۹۱۲۱۲۳۴۵۶۷', '09121234567'],
            ['+98 912 123 4567', '09121234567'],
            ['00989121234567', '09121234567'],
            ['989121234567', '09121234567'],
            ['9121234567', '09121234567'],
            ['0912-123-4567', '09121234567'],
            ['02112345678', null],
            ['0912123456', null],
            ['abc', null],
            ['', null],
        ];
    }

    #[DataProvider('phones')]
    public function test_normalizes_iranian_mobiles(string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public function test_parse_list_reports_invalid_and_duplicates(): void
    {
        $result = PhoneNumber::parseList("09121234567\n+989121234567\n123\n09351234567, ۰۹۳۵۱۲۳۴۵۶۷");

        $this->assertSame(['09121234567', '09351234567'], $result['valid']);
        $this->assertSame(['123'], $result['invalid']);
        $this->assertSame(['09121234567', '09351234567'], $result['duplicates']);
    }

    public function test_template_placeholders(): void
    {
        $this->assertSame(2, TemplateBody::count('سلام {0} عزیز، سفارش {1} ثبت شد. {0}'));
        $this->assertSame([], TemplateBody::errors('سلام {0} و {1}'));
        $this->assertNotEmpty(TemplateBody::errors('سلام {1}'));          // باید از صفر شروع شود
        $this->assertNotEmpty(TemplateBody::errors('سلام {نام}'));        // متغیر نام‌دار پشتیبانی نمی‌شود
        $this->assertSame('سلام علی، کد 12', TemplateBody::render('سلام {0}، کد {1}', ['علی', '12']));
    }

    public function test_variable_value_validation(): void
    {
        $this->assertNull(TemplateBody::valueError('علی'));
        $this->assertNotNull(TemplateBody::valueError('  '));
        $this->assertNotNull(TemplateBody::valueError('https://example.com'));
        $this->assertNotNull(TemplateBody::valueError('site.ir'));
        $this->assertNotNull(TemplateBody::valueError(str_repeat('ا', 101)));
    }

    public function test_send_result_mapping(): void
    {
        $accepted = MelipayamakCodes::sendResult('4458795541548779652');
        $this->assertSame('accepted', $accepted->status);
        $this->assertSame('4458795541548779652', $accepted->recId);

        $invalidBody = MelipayamakCodes::sendResult('-4');
        $this->assertSame('failed', $invalidBody->status);
        $this->assertTrue($invalidBody->permanent);
        $this->assertTrue($invalidBody->stopBatch);

        $noCredit = MelipayamakCodes::sendResult('2');
        $this->assertFalse($noCredit->permanent);
        $this->assertTrue($noCredit->stopBatch);

        // پاسخ ناشناخته: نامشخص (هرگز خودکار تکرار نمی‌شود)
        $this->assertSame('unknown', MelipayamakCodes::sendResult('987')->status);
    }

    public function test_delivery_status_mapping(): void
    {
        $this->assertSame('delivered', MelipayamakCodes::deliveryStatus(1));
        $this->assertSame('undelivered', MelipayamakCodes::deliveryStatus(2));
        $this->assertNull(MelipayamakCodes::deliveryStatus(8));   // رسیده به مخابرات: هنوز نهایی نیست
        $this->assertNull(MelipayamakCodes::deliveryStatus(999));
    }

    public function test_reads_csv_with_semicolon_delimiter(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "\xEF\xBB\xBFنام;موبایل\nعلی;09121234567\nرضا;9351234567\n");

        $data = SpreadsheetReader::read($path, 'csv');
        unlink($path);

        $this->assertSame(['نام', 'موبایل'], $data['headers']);
        $this->assertSame([['علی', '09121234567'], ['رضا', '9351234567']], $data['rows']);
    }

    public function test_reads_xlsx_shared_strings_and_numbers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="S" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>نام</t></si><si><t>موبایل</t></si><si><t>علی</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
            . '<row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2"><v>9121234567</v></c></row>'
            . '<row r="3"><c r="B3"><v>9.351234567E9</v></c></row>'
            . '</sheetData></worksheet>');
        $zip->close();

        $data = SpreadsheetReader::read($path, 'xlsx');
        unlink($path);

        $this->assertSame(['نام', 'موبایل'], $data['headers']);
        $this->assertSame(['علی', '9121234567'], $data['rows'][0]);
        $this->assertSame(['', '9351234567'], $data['rows'][1]);
        $this->assertSame('09351234567', PhoneNumber::normalize($data['rows'][1][1]));
    }
}
