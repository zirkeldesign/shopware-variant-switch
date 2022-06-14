<?php

declare(strict_types=1);

namespace SasVariantSwitch\DataResolver;

use SasVariantSwitch\SasVariantSwitch;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use SasVariantSwitch\Storefront\Page\ProductListingConfigurationLoader;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Product\Cms\ProductSliderCmsElementResolver as BaseResolver;

class ProductSliderCmsElementResolver extends AbstractCmsElementResolver
{
    private const PRODUCT_SLIDER_ENTITY_FALLBACK = 'product-slider-entity-fallback';

    private BaseResolver $elementResolver;

    private SystemConfigService $systemConfigService;

    private ProductListingConfigurationLoader $listingConfigurationLoader;

    public function __construct(
        BaseResolver $elementResolver,
        ProductListingConfigurationLoader $listingConfigurationLoader,
        SystemConfigService $systemConfigService
    ) {
        $this->elementResolver = $elementResolver;
        $this->listingConfigurationLoader = $listingConfigurationLoader;
        $this->systemConfigService = $systemConfigService;
    }

    public function getType(): string
    {
        return $this->elementResolver->getType();
    }

    public function collect(
        CmsSlotEntity $slot,
        ResolverContext $resolverContext
    ): ?CriteriaCollection {
        $context = $resolverContext->getSalesChannelContext();
        $criteriaCollection = $this->elementResolver->collect($slot, $resolverContext);

        if (! $this->systemConfigService->getBool(SasVariantSwitch::SHOW_ON_PRODUCT_CARD, $context->getSalesChannelId())) {
            return $criteriaCollection->all() ? $criteriaCollection : null;
        }

        $config = $slot->getFieldConfig();
        $products = $config->get('products');

        if (!$products
            || $products->isMapped()
            || $products->getValue() === null
        ) {
            return null;
        }

        foreach ($criteriaCollection as $productCriteria) {
            foreach ($productCriteria as $criteria) {
                $criteria->addAssociation('options.group');
            }
        }

        if ($products->isStatic()
            && $products->getValue()
        ) {
            $criteria = new Criteria($products->getValue());
            $criteria->addAssociation('properties.group');
            $criteriaCollection->add('product-slider' . '_' . $slot->getUniqueIdentifier(), ProductDefinition::class, $criteria);
        }

        return $criteriaCollection->all() ? $criteriaCollection : null;
    }

    public function enrich(
        CmsSlotEntity $slot,
        ResolverContext $resolverContext,
        ElementDataCollection $result
    ): void {
        $config = $slot->getFieldConfig();
        $productConfig = $config->get('products');

        if ($productConfig === null) {
            return;
        }

        if ($productConfig->isProductStream()
            && $productConfig->getValue()
        ) {
            $entitySearchResult = $result->get(self::PRODUCT_SLIDER_ENTITY_FALLBACK . '_' . $slot->getUniqueIdentifier());

            if ($entitySearchResult === null) {
                return;
            }

            $products = $entitySearchResult->getEntities();
            $context = $resolverContext->getSalesChannelContext();

            $this->listingConfigurationLoader->loadListing($products, $context);
        }

        $this->elementResolver->enrich($slot, $resolverContext, $result);
    }
}
