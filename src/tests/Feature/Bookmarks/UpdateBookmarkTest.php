<?php

namespace tests\Feature\Bookmarks;

use App\Models\User;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Str;
use Tests\TestCase;

class UploadBookmarkTest extends TestCase
{
    protected function setUp():void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    } 

    /**
     * 
     * ユーザー認証済み
     * ユーザーIDが作成者と一致する
     * 投稿内容がバリデーションを通る
     * 
     * 保存まで成功する
     * @dataProvider updateBookmarkPutDataProvider
     */
    public function testUpdateCorrect(?string $comment, ?int $category, ?string $result, array $sessionError)
    {
        $user = User::find(1);

        $response = $this->actingAs($user)->from('/bookmarks/create')->put('/bookmarks/1', [
            'comment' => $comment,
            'category' => $category,
        ]);

        if ($result === 'success') {
            $response->assertRedirect('/bookmarks');
            $this->assertDatabaseHas('bookmarks',[
                'id' => 1,
                'comment' => $comment,
                'category_id' => $category,
            ]);
        }

        if ($result === 'error') {
            // 更新失敗したときは元の画面に戻る
            $response->assertRedirect('/bookmarks/create');

            $response->assertSessionHasErrors($sessionError);
            $this->assertDatabaseMissing('bookmarks', [
                'id' => 1,
                'comment' => $comment,
                'category_id' => $category,
            ]);
        }
    }

        /**
     * データプロバイダ
     * @see https://phpunit.readthedocs.io/ja/latest/writing-tests-for-phpunit.html#writing-tests-for-phpunit-data-providers
     *
     * @return array
     */
    public function updateBookmarkPutDataProvider()
    {
        return [
            // $comment, $category, $result(success || error), $sessionError
            [Str::random(10), 1, 'success', []],
            [Str::random(9), 1, 'error', ['comment']],
            [Str::random(1000), 1, 'success', []],
            [Str::random(1001), 1, 'error', ['comment']],
            [Str::random(10), 0, 'error', ['category']],
            [Str::random(9), 0, 'error', ['comment', 'category']],
            [null, 1, 'error', ['comment']],
            [Str::random(10), null, 'error', ['category']],
            [null, null, 'error', ['comment', 'category']],
        ];
    }

      /**
     * ユーザーが未認証
     *
     * →ログインページへのリダイレクト
     */
    public function testFailedWhenLogoutUser()
    {
        $this->put('/bookmarks/1', [
            'comment' => 'ブックマークのテスト用のコメントです',
            'category' => 1,
        ])->assertRedirect('/login');
    }

    /**
     * ログインはしているが他人による実行
     *
     * →ステータス403で失敗
     */
    public function testFailedWhenOtherUser()
    {
        $user = User::find(2);
        $this->actingAs($user)->put('/bookmarks/1', [
            'comment' => 'ブックマークのテスト用のコメントです',
            'category' => 1,
        ])->assertForbidden();
    }
} 