<?php

require_once "Zend/Date.php";

/**
 * Show all pages that need to be reviewed.
 */
class PagesDueForReviewReport extends SS_Report
{
    /**
     * @return string
     */
    public function title()
    {
        return _t("PagesDueForReviewReport.TITLE", "Pages due for review");
    }

    /**
     * @return FieldList
     */
    public function parameterFields()
    {
        $filtersList = new FieldList();

        $filtersList->push(new CheckboxField("ShowVirtualPages", _t("PagesDueForReviewReport.SHOWVIRTUALPAGES", "Show Virtual Pages")));

        $filtersList->push(new CheckboxField("OnlyMyPages", _t("PagesDueForReviewReport.ONLYMYPAGES", "Only Show pages assigned to me")));

        return $filtersList;
    }

    /**
     * @return array
     */
    public function columns()
    {
        $linkBase = singleton("CMSPageEditController")->Link("show");
        $linkPath = parse_url($linkBase, PHP_URL_PATH);
        $linkQuery = parse_url($linkBase, PHP_URL_QUERY);

        $fields = array(
            "Title" => array(
                "title" => "Page name",
                "formatting" => "<a href='{$linkPath}/\$ID?{$linkQuery}' title='Edit page'>\$value</a>"
            ),
            "LastReviewDate" => array(
                "title" => "Last reviewed",
                "casting" => "Date->Full",
                "formatting" => function ($value, $item) {
                    return $item->obj("LastReviewDate")->Full();
                }
            ),
            "OwnerNames" => array(
                "title" => "Owner"
            ),
            "LastEditedByName" => "Last edited by",
            "AbsoluteLink" => array(
                "title" => "URL",
                "formatting" => function ($value, $item) {
                    $liveLink = $item->AbsoluteLiveLink;
                    $stageLink = $item->AbsoluteLink();

                    return sprintf("%s <a href='%s'>%s</a>",
                        $stageLink,
                        $liveLink ? $liveLink : $stageLink . "?stage=Stage",
                        $liveLink ? "(live)" : "(draft)"
                    );
                }
            ),
            "ContentReviewType" => array(
                "title" => "Settings are",
                "formatting" => function ($value, $item) use ($linkPath, $linkQuery) {
                    if ($item->ContentReviewType == "Inherit") {
                        $options = $item->getOptions();
                        if ($options && $options instanceof SiteConfig) {
                            return "Inherited from <a href='admin/settings'>Settings</a>";
                        } elseif ($options) {
                            return sprintf(
                                "Inherited from <a href='%s/%d?%s'>%s</a>",
                                $linkPath,
                                $options->ID,
                                $linkQuery,
                                $options->Title
                            );
                        }
                    }

                    return $value;
                }
            ),
        );

        return $fields;
    }

    /**
     * @param array $params
     *
     * @return SS_List
     */
    public function sourceRecords($params = array())
    {
        Versioned::reading_stage("Live"); // No need to review draft content

        $records = SiteTreeContentReview::getPagesForReview();
        $compatibility = ContentReviewCompatability::start();

        // Show virtual pages?
        if (empty($params["ShowVirtualPages"])) {
            $virtualPageClasses = ClassInfo::subclassesFor("VirtualPage");
            $records = $records->exclude('SiteTree', $virtualPageClasses);
        }

        // Only show pages assigned to the current user?
        if (!empty($params["OnlyMyPages"])) {
            $currentUser = Member::currentUser();

            $records = $records->filterByCallback(function ($page) use ($currentUser) {
                $options = $page->getOptions();

                foreach ($options->ContentReviewOwners() as $owner) {
                    if ($currentUser->ID == $owner->ID) {
                        return true;
                    }
                }

                return false;
            });
        }

        ContentReviewCompatability::done($compatibility);

        return $records;
    }
}
