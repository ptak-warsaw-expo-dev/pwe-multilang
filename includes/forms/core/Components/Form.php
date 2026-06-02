<?php
if (!defined('ABSPATH')) exit;

final class PWE_Multilang_GF_Form {

    private array $data = [];

    public static function make() : self {
        return new self();
    }

    public function metaSettings(array $metaSettings) : self {
        $this->data = array_replace_recursive($this->data, $metaSettings);
        return $this;
    }

    public function fields(array $fields) : self {
        $this->data['fields'] = $fields;
        return $this;
    }

    public function confirmations(array $confirmations) : self {
        $this->data['confirmations'] = $confirmations;
        return $this;
    }

    public function notifications(array $notifications) : self {
        $this->data['notifications'] = $notifications;
        return $this;
    }

    public function build() : array {
        return $this->data;
    }
}
