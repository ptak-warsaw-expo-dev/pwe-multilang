<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read-only provider around an unchanged legacy form template.
 */
final class PWE_Multilang_Form_Template_Provider_Adapter
{
    private string $slug;
    private string $file;
    private ?string $className = null;
    private bool $classResolved = false;
    private array $payloadCache = [];

    public function __construct(string $slug, string $file)
    {
        $this->slug = $slug;
        $this->file = $file;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function className(): ?string
    {
        if ($this->classResolved) {
            return $this->className;
        }

        $this->classResolved = true;
        $before = get_declared_classes();
        require_once $this->file;
        $after = get_declared_classes();

        foreach (array_diff($after, $before) as $class) {
            if (method_exists($class, 'apply')) {
                $this->className = $class;
                return $this->className;
            }
        }

        foreach (get_declared_classes() as $class) {
            if (!method_exists($class, 'apply') || strpos($class, 'PWE_Multilang_Form_Template_') !== 0) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (self::normalizePath((string) $reflection->getFileName()) === self::normalizePath($this->file)) {
                $this->className = $class;
                return $this->className;
            }
        }

        return null;
    }

    public function payload(int $formsYear): array
    {
        if (array_key_exists($formsYear, $this->payloadCache)) {
            return $this->payloadCache[$formsYear];
        }

        $class = $this->className();

        if ($class === null) {
            $this->payloadCache[$formsYear] = [];
            return [];
        }

        $payload = PWE_Multilang_Form_Template_Capture::capture($class, $formsYear);
        $payload[PWE_Multilang_Form_Core::MANAGED_FLAG] = 1;
        $payload[PWE_Multilang_Form_Identity::TEMPLATE_SLUG_KEY] = $this->slug;

        $this->payloadCache[$formsYear] = $payload;

        return $payload;
    }

    private static function normalizePath(string $path): string
    {
        $realPath = realpath($path);
        $path = $realPath !== false ? $realPath : $path;
        $path = str_replace('\\', '/', $path);

        return DIRECTORY_SEPARATOR === '\\' ? mb_strtolower($path) : $path;
    }
}
