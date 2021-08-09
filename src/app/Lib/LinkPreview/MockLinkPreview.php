<?php

namespace App\Lib\LinkPreview;

final class MockLinkPreview implements LinkPreviewInterface
{
    public function get(string $url):GetLinkPreviewResponse
    {
        return new GetLinkPreviewResponse(
            'モックのタイトル',
            'モックのdescription',
            'https://user-images.githubusercontent.com/42291263/85671906-522ec780-b6fd-11ea-8c23-eca15138646b.png',
        );
    }
}