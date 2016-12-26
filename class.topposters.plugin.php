<?php

$PluginInfo['topPosters'] = [
    'Name' => 'Top Posters',
    'Description' => 'List users with most posts in a given period. Results are cached and cache is invalidated every 2 minutes.',
    'Version' => '0.1',
    'HasLocale' => true,
    'Author' => 'Robin Jurinka',
    'AuthorUrl' => 'https://open.vanillaforums.com/profile/R_J',
    'RequiredApplications' => ['Vanilla' => '>=2.3'],
    'RegisterPermissions' => ['Plugins.TopPosters.View'],
    'SettingsPermission' => [
        'Garden.Settings.Manage',
        'Garden.Community.Manage'
    ],
    'RequiredTheme' => false,
    'RequiredPlugins' => false,
    'MobileFriendly' => true,
    'License' => 'MIT',
];

class TopPostersPlugin extends Gdn_Plugin {

    public function setup() {
        // Create custom route.
        $newRoute = '^topposters(/.*)?$';
        if (!Gdn::router()->matchRoute($newRoute)) {
            Gdn::router()->setRoute($newRoute, 'vanilla/topposters$1', 'Internal');
        }
    }

    /**
     * Clean up when plugin is disabled.
     *
     * @return void.
     */
    public function onDisable() {
        Gdn::router()->deleteRoute('^topposters(/.*)?$');
    }

    public function base_render_before($sender) {
        if (!checkPermission('Plugins.TopPosters.View')) {
            return;
        }
        if ($sender->MasterView == 'default' || $sender->MasterView == '') {
            $sender->Menu->addLink('TopPosters', t('Top Posters'), 'topposters');
        }
    }

    public function vanillaController_topPosters_create($sender, $args = '') {
        $sender->permission('Plugins.TopPosters.View');

        // Find which page to show and which filter to apply.
        $page = strtolower($args[0]);
        $filter = 'all';
        if (in_array($page, ['all', 'currentweek', 'previousweek'])) {
            $filter = $page;
            $page = val($args[1], '');
        }

        // Determine offset from $page.
        list($offset, $limit) = offsetLimit($page, c('topPosters.usersPerPage', 30), true);
        $page = pageNumber($offset, $limit);

        // Set canonical URL.
        $sender->canonicalUrl(url(concatSep('/', ['topposters', $filter], pageNumber($offset, $limit, true, false)), true));

        // Setup head.
        $sender->setData('Title', t('Top Posters'));
        $sender->Form = new Gdn_Form();
        $sender->Form->setValue('Filter', $filter);
        $sender->setData(
            'Filter',
            [
                'all' => t('all time'),
                'currentweek' => t('current week'),
                'previousweek' => t('previous week')
            ]
        );
        // Redirect based on filter
        if ($sender->Form->isPostBack() && $sender->Form->getValue('GO!') != '') {
            redirect(url('/topposters/'.$sender->Form->getValue('Filter')));
        }

        // Add modules
        $sender->addModule('DiscussionFilterModule');
        $sender->addModule('NewDiscussionModule');
        $sender->addModule('CategoriesModule');
        $sender->addModule('BookmarkedModule');
        $sender->setData('Breadcrumbs', [['Name' => t('Top Posters'), 'Url' => '/topposters']]);

        $topPosters = Gdn::cache()->get($filter.'TopPosters');
        if ($topPosters == Gdn_Cache::CACHEOP_FAILURE) {
            $wheres = '';
            switch ($filter) {
                case 'currentweek':
                    $wheres = "WHERE DateInserted >= '".date('Y-m-d 00:00:00', strtotime('-1 week'))."'\r\n";
                    break;
                case 'previousweek':
                    $wheres = 'WHERE DateInserted < ';
                    $wheres .= "'".date('Y-m-d 00:00:00', strtotime('-1 week'))."'\r\n";
                    $wheres .= '  AND DateInserted >= ';
                    $wheres .= "'".date('Y-m-d 00:00:00', strtotime('-2 week'))."'\r\n";
                    break;
                default:
            }

            if (c('topPosters.UserIDsToExclude', false)) {
                // Never trust user input, so sanitize.
                $userIDs = explode(',', c('topPosters.UserIDsToExclude', false));
                $userIDs = array_map('intval', $userIDs);
                if ($wheres == '') {
                    $wheres = 'WHERE ';
                } else {
                    $wheres .= '  AND ';
                }
                $wheres .= 'InsertUserID NOT IN ('.implode(',', $userIDs).")\r\n";
            }

            $px = Gdn::structure()->databasePrefix();
            $sql = <<<EOS
SELECT
    u.Name
    , u.UserID
    , COALESCE(CommentCount, 0) AS `CommentCount`
    , COALESCE(DiscussionCount, 0) AS `DiscussionCount`
    , COALESCE(CommentCount, 0) + COALESCE(DiscussionCount, 0) AS `PostCount`
FROM
    {$px}User u
LEFT JOIN (
    SELECT InsertUserID, COUNT(CommentID) AS `CommentCount`
    FROM GDN_Comment
    {$wheres}
    GROUP BY InsertUserID
) c
    ON u.UserID = c.InsertUserID
LEFT JOIN (
    SELECT InsertUserID, count(DiscussionID) `DiscussionCount`
    FROM GDN_Discussion
    {$wheres}
    GROUP BY InsertUserID
) d
    ON u.UserID = d.InsertUserID
WHERE
    COALESCE(CommentCount, 0) + COALESCE(DiscussionCount, 0) > 0
EOS;

            $topPosters = Gdn::sql()->query($sql)->resultArray();
            usort($topPosters, function ($a, $b) {
                return $b['PostCount'] - $a['PostCount'];
            });

            Gdn::cache()->store(
                $filter.'TopPosters',
                $topPosters,
                [Gdn_Cache::FEATURE_EXPIRY => 120]
            );
        }

        $sender->setData('TopPoster', $topPosters);
        switch ($filter) {
            case 'currentweek':
                $sender->setData(
                    [
                        'CssAllFilter' => '',
                        'CssCurrentWeekFilter' => ' class="Active"',
                        'CssPreviousWeekFilter' => ''
                    ]
                );
                break;
            case 'previousweek':
                $sender->setData(
                    [
                        'CssAllFilter' => '',
                        'CssCurrentWeekFilter' => '',
                        'CssPreviousWeekFilter' => ' class="Active"'
                    ]
                );
                break;
            default:
                $sender->setData(
                    [
                        'CssAllFilter' => ' class="Active"',
                        'CssCurrentWeekFilter' => '',
                        'CssPreviousWeekFilter' => ''
                    ]
                );
        }

        $sender->render($this->getView('topposters.php'));
    }
}
