<?php

namespace tests\Feature\Bookmarks;

use Tests\TestCase;

class ShowBookmarkListCategoryPageTest extends TestCase
{
    protected function setUp():void
    {
        parent::setUp();
    }

    public function testCorrectListCategoryByCategory1()
    {
        $response = $this->get('/bookmarks/category/1');
        $response->assertStatus(200);
        $response->assertViewHas('h1','HTMLのブックマーク一覧');
        $response->assertViewIs('page.bookmark_list.index');
        $response->assertSee('<meta name="description" content="HTMLに特化したブックマーク一覧です。みんなが投稿したHTMLのブックマークが投稿順に並んでいます。全部で13件のブックマークが投稿されています">');
        $response->assertViewHas('bookmarks');

        $bookmarks = $response->viewData('bookmarks');
        $top_categories = $response->viewData('top_categories');
        $top_users = $response->viewData('top_users');

        // それぞれ10件ずつ取得できているかを確認する
        self::assertCount(10, $bookmarks);
        self::assertCount(10, $top_categories);
        self::assertCount(10, $top_users);

        foreach ($bookmarks as $bookmark) {
            // カテゴリ1のレコードであるかを確認する
            $this->assertSame(1, $bookmark->category_id);
        }

        foreach ($top_categories as $top_category) {
            // カテゴリ1以外のレコードであることを確認する
            $this->assertTrue($top_category->id !== 1);
        }
    }

    public function testNotNumericCategoryInput()
    {
        $response = $this->get('/bookmarks/category/a');
        $response->assertStatus(404);
    }
}