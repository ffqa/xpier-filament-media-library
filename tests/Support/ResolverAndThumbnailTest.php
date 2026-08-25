<?php

namespace Xpier\FilamentMediaLibrary\Tests\Support;

use Xpier\FilamentMediaLibrary\Support\MediaUrlResolver;
use Xpier\FilamentMediaLibrary\Support\Providers\CosThumbnailProvider;
use Xpier\FilamentMediaLibrary\Support\Providers\LocalThumbnailProvider;
use Xpier\FilamentMediaLibrary\Support\Providers\MultiDiskUrlResolver;
use Xpier\FilamentMediaLibrary\Tests\TestCase;

class ResolverAndThumbnailTest extends TestCase
{
    public function test_multi_disk_resolver_returns_null_when_unconfigured(): void
    {
        $resolver = new MultiDiskUrlResolver;

        $this->assertNull($resolver->url('s3', 'media/test.png'));
    }

    public function test_multi_disk_resolver_maps_disk_to_public_url(): void
    {
        $resolver = new MultiDiskUrlResolver(
            publicUrls: ['private_s3' => 'https://cdn.example.com/'],
        );

        $this->assertSame(
            'https://cdn.example.com/media/test.png',
            $resolver->url('private_s3', 'media/test.png'),
        );
    }

    public function test_multi_disk_resolver_falls_back_to_default_public_url(): void
    {
        $resolver = new MultiDiskUrlResolver(
            publicUrls: ['private_s3' => 'https://cdn.example.com'],
            defaultPublicUrl: 'https://fallback.example.com',
        );

        $this->assertSame(
            'https://fallback.example.com/media/test.png',
            $resolver->url('other_disk', 'media/test.png'),
        );
        $this->assertSame(
            'https://cdn.example.com/media/test.png',
            $resolver->url('private_s3', 'media/test.png'),
        );
    }

    public function test_container_resolves_multi_disk_resolver_from_config(): void
    {
        config()->set('filament-media-library.public_urls', ['s3' => 'https://cdn.example.com']);

        $resolver = app(MediaUrlResolver::class);

        $this->assertInstanceOf(MultiDiskUrlResolver::class, $resolver);
        $this->assertSame('https://cdn.example.com/media/a.png', $resolver->url('s3', 'media/a.png'));
    }

    public function test_container_resolves_custom_resolver_class(): void
    {
        config()->set('filament-media-library.url_resolver', CustomTestResolver::class);

        $resolver = app(MediaUrlResolver::class);

        $this->assertSame('https://custom.example.com/media/a.png', $resolver->url('any', 'media/a.png'));
    }

    public function test_local_thumbnail_provider_returns_original_url(): void
    {
        $provider = new LocalThumbnailProvider;

        $this->assertSame('https://cdn.example.com/img.png', $provider->thumbnail('https://cdn.example.com/img.png', 320, 80));
        $this->assertNull($provider->thumbnail(null));
    }

    public function test_cos_thumbnail_provider_generates_image_mogr2_url(): void
    {
        $provider = new CosThumbnailProvider;

        $url = $provider->thumbnail('https://bucket-123.cos.ap-guangzhou.myqcloud.com/media/img.png', 400, 75);

        $this->assertStringContainsString('imageMogr2/thumbnail/400x', $url);
        $this->assertStringContainsString('quality/75', $url);
    }

    public function test_cos_thumbnail_provider_ignores_non_cos_urls(): void
    {
        $provider = new CosThumbnailProvider;

        $this->assertSame(
            'https://cdn.example.com/img.png',
            $provider->thumbnail('https://cdn.example.com/img.png', 400, 75),
        );
    }

    public function test_cos_thumbnail_provider_handles_public_urls_mapped_domain(): void
    {
        config()->set('filament-media-library.public_urls', [
            'cos' => 'https://cdn.example.com',
        ]);

        $provider = new CosThumbnailProvider;

        $url = $provider->thumbnail('https://cdn.example.com/media/img.png', 400, 75);

        $this->assertStringContainsString('imageMogr2/thumbnail/400x', $url);
    }

    public function test_cos_thumbnail_provider_respects_image_process_switch(): void
    {
        config()->set('filament-media-library.image_process', false);

        $provider = new CosThumbnailProvider;

        $this->assertSame(
            'https://bucket-123.cos.ap-guangzhou.myqcloud.com/media/img.png',
            $provider->thumbnail('https://bucket-123.cos.ap-guangzhou.myqcloud.com/media/img.png', 400, 75),
        );
    }
}

class CustomTestResolver implements MediaUrlResolver
{
    public function url(string $disk, string $path): ?string
    {
        return 'https://custom.example.com/'.ltrim($path, '/');
    }
}
