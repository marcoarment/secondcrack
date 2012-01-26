<?php
class Dummy extends Hook
{
    public function doHook(Post $post)
    {
        error_log('Hooked ' . $post->title);
    }
}
