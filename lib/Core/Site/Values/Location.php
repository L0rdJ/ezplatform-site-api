<?php

namespace Netgen\EzPlatformSiteApi\Core\Site\Values;

use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\ContentTypeIdentifier;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\LocationId;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\LogicalAnd;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\LogicalNot;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\ParentLocationId;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Visibility;
use Netgen\EzPlatformSiteApi\API\Values\Location as APILocation;
use Netgen\EzPlatformSiteApi\Core\Site\Pagination\Pagerfanta\LocationSearchFilterAdapter;
use Pagerfanta\Pagerfanta;

final class Location extends APILocation
{
    /**
     * @var \Netgen\EzPlatformSiteApi\API\Values\ContentInfo
     */
    protected $contentInfo;

    /**
     * @var \eZ\Publish\API\Repository\Values\Content\Location
     */
    protected $innerLocation;

    /**
     * @var \Netgen\EzPlatformSiteApi\API\Site
     */
    private $site;

    /**
     * @var \Pagerfanta\Pagerfanta[]
     */
    private $childrenPagerCache = [];

    /**
     * @var \Pagerfanta\Pagerfanta[]
     */
    private $siblingPagerCache = [];

    /**
     * @var \Netgen\EzPlatformSiteApi\API\Values\Location
     */
    private $internalParent;

    /**
     * @var \Netgen\EzPlatformSiteApi\API\Values\Content
     */
    private $internalContent;

    public function __construct(array $properties = [])
    {
        if (array_key_exists('site', $properties)) {
            $this->site = $properties['site'];
            unset($properties['site']);
        }

        parent::__construct($properties);
    }

    /**
     * {@inheritdoc}
     *
     * Magic getter for retrieving convenience properties.
     *
     * @param string $property The name of the property to retrieve
     *
     * @return mixed
     */
    public function __get($property)
    {
        switch ($property) {
            case 'contentId':
                return $this->contentInfo->id;
            case 'parent':
                return $this->getParent();
            case 'content':
                return $this->getContent();
        }

        if (property_exists($this, $property)) {
            return $this->$property;
        }

        if (property_exists($this->innerLocation, $property)) {
            return $this->innerLocation->$property;
        }

        return parent::__get($property);
    }

    /**
     * Magic isset for signaling existence of convenience properties.
     *
     * @param string $property
     *
     * @return bool
     */
    public function __isset($property)
    {
        switch ($property) {
            case 'contentId':
            case 'parent':
            case 'content':
                return true;
        }

        if (property_exists($this, $property) || property_exists($this->innerLocation, $property)) {
            return true;
        }

        return parent::__isset($property);
    }

    public function getChildren($limit = 25)
    {
        return $this->filterChildren([], $limit)->getIterator();
    }

    public function filterChildren(array $contentTypeIdentifiers = [], $maxPerPage = 25, $currentPage = 1)
    {
        $cacheId = $this->getCacheId($contentTypeIdentifiers, $maxPerPage);

        if (!array_key_exists($cacheId, $this->childrenPagerCache)) {
            $criteria = [
                new ParentLocationId($this->id),
                new Visibility(Visibility::VISIBLE),
            ];

            if (!empty($contentTypeIdentifiers)) {
                $criteria[] = new ContentTypeIdentifier($contentTypeIdentifiers);
            }

            $pager = new Pagerfanta(
                new LocationSearchFilterAdapter(
                    new LocationQuery([
                        'filter' => new LogicalAnd($criteria),
                        'sortClauses' => $this->innerLocation->getSortClauses(),
                    ]),
                    $this->site->getFilterService()
                )
            );

            $pager->setNormalizeOutOfRangePages(true);
            $pager->setMaxPerPage($maxPerPage);

            $this->childrenPagerCache[$cacheId] = $pager;
        }

        $this->childrenPagerCache[$cacheId]->setCurrentPage($currentPage);

        return $this->childrenPagerCache[$cacheId];
    }

    public function getSiblings($limit = 25)
    {
        return $this->filterSiblings([], $limit)->getIterator();
    }

    public function filterSiblings(array $contentTypeIdentifiers = [], $maxPerPage = 25, $currentPage = 1)
    {
        $cacheId = $this->getCacheId($contentTypeIdentifiers, $maxPerPage);

        if (!array_key_exists($cacheId, $this->siblingPagerCache)) {
            $criteria = [
                new ParentLocationId($this->parentLocationId),
                new LogicalNot(
                    new LocationId($this->id)
                ),
                new Visibility(Visibility::VISIBLE),
            ];

            if (!empty($contentTypeIdentifiers)) {
                $criteria[] = new ContentTypeIdentifier($contentTypeIdentifiers);
            }

            $pager = new Pagerfanta(
                new LocationSearchFilterAdapter(
                    new LocationQuery([
                        'filter' => new LogicalAnd($criteria),
                        'sortClauses' => $this->innerLocation->getSortClauses(),
                    ]),
                    $this->site->getFilterService()
                )
            );

            $pager->setNormalizeOutOfRangePages(true);
            $pager->setMaxPerPage($maxPerPage);

            $this->siblingPagerCache[$cacheId] = $pager;
        }

        $this->siblingPagerCache[$cacheId]->setCurrentPage($currentPage);

        return $this->siblingPagerCache[$cacheId];
    }

    /**
     * Returns unique string for the given parameters.
     *
     * @param array $contentTypeIdentifiers
     * @param int $maxPerPage
     *
     * @return string
     */
    private function getCacheId(array $contentTypeIdentifiers, $maxPerPage)
    {
        sort($contentTypeIdentifiers);

        return md5(implode(' ', $contentTypeIdentifiers) . ' ' . $maxPerPage);
    }

    private function getParent()
    {
        if ($this->internalParent === null) {
            $this->internalParent = $this->site->getLoadService()->loadLocation(
                $this->parentLocationId
            );
        }

        return $this->internalParent;
    }

    private function getContent()
    {
        if ($this->internalContent === null) {
            $this->internalContent = $this->site->getLoadService()->loadContent(
                $this->contentInfo->id
            );
        }

        return $this->internalContent;
    }
}
