<?php
namespace tests\Feature\Bookmarks;

use App\Bookmark\UseCase\CreateBookmarkUseCase;
use App\Lib\LinkPreview\LinkPreviewInterface;
use App\Lib\LinkPreview\MockLinkPreview;
use App\Models\BookmarkCategory;
use App\Models\Bookmark;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Exception;

class CreateBookmarkUseCaseTest extends TestCase
{
    private CreateBookmarkUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(LinkPreviewInterface::class, MockLinkPreview::class);
        $this->useCase = $this->app->make(CreateBookmarkUseCase::class);
    }

    public function testSaveCorrectData()
    {
        // 絶対に存在しないURL(example.comは使えないドメイン)
        $url = 'https://notfound.example.com/';
        $category = BookmarkCategory::first()->id;
        $comment = 'テスト用のコメント';

        $user = User::first();
        Auth::loginUsingId($user->id);

        $this->useCase->handle($url, $category, $comment);

        Auth::logout();

        // データベースに保存された値を期待したとおりかどうかチェックする
        $this->assertDatabaseHas('bookmarks', [
            'url' => $url,
            'category_id' => $category,
            'user_id' => $user->id,
            'comment' => $comment,
            'page_title' => 'モックのタイトル',
            'page_description' => 'モックのdescription',
            'page_thumbnail_url' => 'https://user-images.githubusercontent.com/42291263/85671906-522ec780-b6fd-11ea-8c23-eca15138646b.png',
        ]);
    }

    public function testWhenFetchMetaFailed()
    {
        $url = 'https://notfound.example.com/';
        $category = BookmarkCategory::first()->id;
        $comment = 'テスト用のコメント';

        // これまでと違ってMockeryというライブラリでモックを用意する
        $mock = \Mockery::mock(LinkPreviewInterface::class);

        // 作ったモックがgetメソッドを実行したら必ず例外を投げるように仕込む
        $mock->shouldReceive('get')
            ->withArgs([$url])
            ->andThrow(new \Exception('URLからメタ情報の取得に失敗'))
            ->once();

        // サービスコンテナに$mockを使うように命令する
        $this->app->instance(
            LinkPreviewInterface::class,
            $mock
        );

        // 例外が投げられることのテストは以下のように書く
        $this->expectException(ValidationException::class);
        $this->expectExceptionObject(ValidationException::withMessages([
            'url' => 'URLが存在しない等の理由で読み込めませんでした。変更して再度投稿してください'
        ]));

        // 仕込みが終わったので実際の処理を実行
        $this->useCase = $this->app->make(CreateBookmarkUseCase::class);
        $this->useCase->handle($url, $category, $comment);
    }
}
