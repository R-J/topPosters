<?php defined('APPLICATION') or die; ?>
<style>
.TopPosters .MItem {display: inline-block; width: 20%;}
.TopPosters .MItem a:first-child {margin-right: 0.5em;}
#Form_Filter {display: inline-block; width: auto;}
</style>
<h1><?= t('Top Posters') ?></h1>

<?= $this->Form->open(); ?>
<p>
<?= t('Show top posters of ') ?>
<?= $this->Form->dropDown('Filter', $this->data('Filter')) ?>
<?= $this->Form->button('GO!') ?>
</p>
<?= $this->Form->close() ?>

<ul class="DataList TopPosters">
    <li class="Item Title">
        <span class="MItem"><?= t('User') ?></span>
        <span class="MItem"><?= t('Comments') ?></span>
        <span class="MItem"><?= t('Discussions') ?></span>
        <span class="MItem"><?= t('Sum') ?></span>
    </li>
    <?php
    foreach ($this->data('TopPoster') as $poster) {
        $user = Gdn::userModel()->getID($poster['UserID']);
    ?>
    <li class="Item">
        <span class="MItem">
            <?php
            if (c('Vanilla.Comment.UserPhotoFirst', true)) {
                echo userPhoto($user, ['Size' => 'Small']).userAnchor($user, 'Username');
            } else {
                echo userAnchor($user, 'Username').userPhoto($user, ['Size' => 'Small']);
            }
            ?>
        </span>
        <span class="MItem">
            <?= anchor($poster['CommentCount'], userUrl($user, '', 'comments')) ?>
        </span>
        <span class="MItem">
            <?= anchor($poster['DiscussionCount'], userUrl($user, '', 'discussions')) ?>
        </span>
        <span class="MItem"><?= $poster['PostCount'] ?></span>
    </li>
    <?php } ?>
</ul>
