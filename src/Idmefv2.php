<?php
namespace GlpiPlugin\Idmefv2;

use InvalidArgumentException;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use LogicException;
use Plugin;
use RuntimeException;
use stdClass;

use function Safe\file_get_contents;
use function Safe\json_decode;

class Idmefv2
{
    /**
     * @param array $message an array representation of a IDMEFv2 message (json_decoded)
     */
    public function getVersion(stdClass $message): string
    {
        $version = $message->Version ?? 'latest';
        // Check that $version contains only digits, dots and letters D and V
        $allowed_chars = '0123456789.DV';
        $cleaned = str_replace(str_split($allowed_chars), '', $version);
        if (strlen($cleaned) !== 0) {
            throw new InvalidArgumentException('Invalid IDMEFv2 version identifier');
        }

        return $version;
    }

    /**
     * get IDMEFv2 specification file by given version
     * @param string $version
     */
    public function getSpecificationFilePath(string $version = 'latest'): string
    {
        $file = 'IDMEFv' . $version . '.schema';
        $path = Plugin::getPhpDir('idmefv2') . '/spec/' . $file;

        return $path;
    }

    public function isIdmefv2Compliant(stdClass $message): bool
    {
        try {
            $version = $this->getVersion($message);
        } catch (LogicException $e) {
            return false;
        }
        $path = $this->getSpecificationFilePath($version);
        try {
            $schema_string = file_get_contents($path);
            $schema = json_decode($schema_string);
        } catch (RuntimeException $e) {
            return false;
        }

        $validator = new Validator();
        $validator->validate($message, $schema, Constraint::CHECK_MODE_NORMAL);
        $errors = $validator->getErrors();
        return $validator->isValid();
    }
}