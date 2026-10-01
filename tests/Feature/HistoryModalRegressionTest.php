<?php

namespace Tests\Feature;

use Tests\TestCase;

class HistoryModalRegressionTest extends TestCase
{
    public function test_history_modal_hidden_state_is_protected_from_css_override(): void
    {
        $css = file_get_contents(resource_path('css/pages/history.css'));

        $this->assertNotFalse($css);
        $this->assertStringContainsString('.detail-modal[hidden]', $css);
        $this->assertStringContainsString('display: none', $css);
    }
}
