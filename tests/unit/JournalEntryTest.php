<?php declare(strict_types=1);
namespace theseer\journald;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use function array_keys;
use function str_repeat;
use function uniqid;

/**
 * @covers \theseer\journald\JournalEntry
 */
class JournalEntryTest extends TestCase {

    public function testIsProperlyConstructedFromMessage(): void {

        $msg = uniqid('test', true);

        $entry = JournalEntry::fromMessage($msg);

        $entryAsArray = $this->entryAsArray($entry);

        $this->assertSame(
            ['MESSAGE_ID', 'MESSAGE', 'CODE_FILE', 'CODE_LINE', 'CODE_FUNC'],
            array_keys($entryAsArray)
        );

        $this->assertSame($msg, $entryAsArray['MESSAGE']);
    }

    public function testCanBeCreatedFromThrowable(): void {
        $msg = uniqid('test', true);

        $entry = JournalEntry::fromThrowable(
            new RuntimeException($msg)
        );

        $entryAsArray = $this->entryAsArray($entry);

        $this->assertSame(
            ['MESSAGE_ID', 'MESSAGE', 'CODE_FILE', 'CODE_LINE', 'CODE_FUNC','ERRNO', 'CLASS', 'TRACE'],
            array_keys($entryAsArray)
        );

        $this->assertSame($msg, $entryAsArray['MESSAGE']);
    }

    /**
     * @dataProvider invalidFieldnameProvider
     */
    public function testAddingValueWithInvalidFieldNameThrowsException(string $fieldname): void {
        $entry = JournalEntry::fromMessage('test');

        $this->expectException(JournalEntryException::class);
        $entry->addValue($fieldname, 'value');
    }

    public function testTryingToOverwriteExistingFieldThrowsException(): void {
        $entry = JournalEntry::fromMessage('test');

        $this->expectException(JournalEntryException::class);
        $entry->addValue('MESSAGE', 'value');
    }

    public function testSimpleValuesCanBeSerializedToJournaldFormatedString(): void {
        $expected = implode("\n",[
            "MESSAGE_ID=%s",
            "MESSAGE=test",
            "CODE_FILE=" . __FILE__,
            "CODE_LINE=" . (__LINE__ + 4),
            "CODE_FUNC=theseer\journald\JournalEntry::fromMessage",
            ""
        ]);
        $entry = JournalEntry::fromMessage('test');

        $this->assertStringMatchesFormat($expected, $entry->asString());
    }

    public function testValuesWithLinebreaksCanBeSerializedToJournaldFormatedString(): void {
        $message = "line1\nline2";
        $expected = implode("\n",[
            "MESSAGE_ID=%s",
            "MESSAGE",
            pack('P', strlen($message)) . "line1",
            "line2",
            "CODE_FILE=" . __FILE__,
            "CODE_LINE=" . (__LINE__ + 4),
            "CODE_FUNC=theseer\journald\JournalEntry::fromMessage",
            ""
        ]);
        $entry = JournalEntry::fromMessage($message);

        $this->assertStringMatchesFormat($expected, $entry->asString());
    }

    private function entryAsArray(JournalEntry $entry): array {
        $entryAsArray = [];
        foreach ($entry as $key => $value) {
            $entryAsArray[$key] = $value;
        }
        return $entryAsArray;
    }

    public function invalidFieldnameProvider(): array {
        return [
            'too-long' => [str_repeat('X', 65)],
            'start-with-underscore' => ['_FOO'],
            'start-with-digit' => ['0FOO'],
            'non-ascii-chars' => ['F?=']
        ];
    }

}
