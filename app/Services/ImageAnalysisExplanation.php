<?php

namespace App\Services;

class ImageAnalysisExplanation
{
    public function normalizeLabel(?string $label): ?string
    {
        return match (strtoupper(trim((string) $label))) {
            'AI-GENERATED', 'ARTIFICIAL' => 'AI-GENERATED',
            'AI-EDITED' => 'AI-EDITED',
            'REAL', 'HUMAN' => 'REAL',
            'UNCERTAIN' => 'UNCERTAIN',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function build(?string $label, ?float $score, array $evidence = []): string
    {
        $normalizedLabel = $this->normalizeLabel($label);
        $probability = $score !== null && is_finite($score) && $score >= 0 && $score <= 1
            ? number_format($score * 100, 2, '.', '').'%'
            : null;
        $basis = is_string($evidence['basis'] ?? null)
            ? strtolower($evidence['basis'])
            : '';
        $metadataSaysAi = ($evidence['metadata_says_ai'] ?? false) === true;
        $probabilityText = $probability === null
            ? ''
            : ' Estimasi probabilitas AI gabungan: '.$probability.'.';

        return match ($normalizedLabel) {
            'AI-GENERATED' => $metadataSaysAi
                ? 'Hasil analisis mengarah bahwa gambar ini kemungkinan dibuat menggunakan AI. '
                    .$this->provenanceDescription($basis)
                    .' Klaim provenance merupakan informasi yang tertanam pada berkas gambar, bukan verifikasi independen atas asal gambar.'
                    .$probabilityText
                : 'Hasil analisis visual mengarah bahwa gambar ini kemungkinan dibuat menggunakan AI karena skor gabungan mencapai batas keputusan kategori tersebut.'
                    .$probabilityText,
            'AI-EDITED' => $metadataSaysAi && str_contains($basis, 'generated region in an otherwise real image')
                ? 'Hasil analisis mengarah bahwa gambar ini kemungkinan memiliki bagian yang dibuat atau diubah menggunakan AI, sementara bagian lainnya terindikasi non-AI (AI-EDITED). '
                    .$this->provenanceDescription($basis)
                    .$probabilityText
                : 'Hasil analisis mengarah bahwa gambar ini kemungkinan memiliki bagian yang dibuat atau diubah menggunakan AI (AI-EDITED).'
                    .$probabilityText,
            'REAL' => 'Hasil analisis lebih mendukung gambar kategori REAL/Human daripada gambar yang sepenuhnya dibuat menggunakan AI.'
                .$probabilityText,
            'UNCERTAIN' => str_contains($basis, 'file is truncated')
                ? 'Kategori gambar belum dapat ditentukan dengan memadai karena file terdeteksi terpotong.'
                    .$probabilityText
                : 'Hasil analisis belum cukup kuat untuk menentukan apakah gambar kemungkinan dibuat menggunakan AI atau termasuk kategori REAL/Human.'
                    .$probabilityText,
            default => 'Kategori hasil analisis tidak tersedia atau tidak dikenali.',
        };
    }

    private function provenanceDescription(string $basis): string
    {
        if (str_contains($basis, 'c2pa manifest declares digitalsourcetype=trainedalgorithmicmedia')) {
            return 'Metadata gambar memuat klaim provenance yang menyebut sumber gambar dibuat dengan AI.';
        }

        return 'Metadata gambar memuat petunjuk provenance terkait AI.';
    }
}
