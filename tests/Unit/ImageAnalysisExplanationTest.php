<?php

namespace Tests\Unit;

use App\Services\ImageAnalysisExplanation;
use PHPUnit\Framework\TestCase;

class ImageAnalysisExplanationTest extends TestCase
{
    public function test_legacy_labels_map_to_the_current_categories(): void
    {
        $builder = new ImageAnalysisExplanation;

        $this->assertSame('AI-GENERATED', $builder->normalizeLabel('artificial'));
        $this->assertSame('REAL', $builder->normalizeLabel('human'));
        $this->assertNull($builder->normalizeLabel('ERROR'));
    }

    public function test_ai_generated_explanation_uses_c2pa_evidence_without_model_names(): void
    {
        $explanation = (new ImageAnalysisExplanation)->build('AI-GENERATED', 0.7993, [
            'basis' => 'metadata describes the whole image: C2PA manifest declares digitalSourceType=trainedAlgorithmicMedia',
            'metadata_says_ai' => true,
        ]);

        $this->assertStringContainsString('kemungkinan dibuat menggunakan AI', $explanation);
        $this->assertStringContainsString('klaim provenance', $explanation);
        $this->assertStringContainsString('79.93%', $explanation);
        $this->assertStringNotContainsString('commforensics', $explanation);
    }

    public function test_ai_edited_explanation_describes_a_generated_region(): void
    {
        $explanation = (new ImageAnalysisExplanation)->build('AI-EDITED', 0.63, [
            'basis' => 'metadata describes a generated region in an otherwise real image: C2PA assertion',
            'metadata_says_ai' => true,
        ]);

        $this->assertStringContainsString('bagian yang dibuat atau diubah menggunakan AI', $explanation);
        $this->assertStringContainsString('bagian lainnya terindikasi non-AI', $explanation);
        $this->assertStringContainsString('63.00%', $explanation);
    }

    public function test_real_and_uncertain_explanations_do_not_claim_provenance(): void
    {
        $builder = new ImageAnalysisExplanation;
        $real = $builder->build('REAL', 0.08);
        $uncertain = $builder->build('UNCERTAIN', 0.5916);

        $this->assertStringContainsString('REAL/Human', $real);
        $this->assertStringContainsString('8.00%', $real);
        $this->assertStringContainsString('belum cukup kuat', $uncertain);
        $this->assertStringContainsString('59.16%', $uncertain);
    }
}
