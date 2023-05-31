<?php

declare(strict_types = 1);

namespace Pagemachine\Formlog\Controller\Backend;

/*
 * This file is part of the Pagemachine TYPO3 Formlog project.
 */

use Pagemachine\Formlog\Domain\FormLog\Filters;
use Pagemachine\Formlog\Domain\Repository\FormLogEntryRepository;
use Pagemachine\Formlog\Export\CsvExport;
use Pagemachine\Formlog\Export\XlsxExport;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Controller for form log management
 */
class FormLogController extends ActionController
{

    protected array $viewFormatToExportMap = [
        'csv' => CsvExport::class,
        'xlsx' => XlsxExport::class,
    ];
    protected ModuleTemplateFactory $moduleTemplateFactory;

    protected FormLogEntryRepository $formLogEntryRepository;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory
    ) {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
    }

    public function injectFormLogEntryRepository(FormLogEntryRepository $formLogEntryRepository)
    {
        $this->formLogEntryRepository = $formLogEntryRepository;
    }

    /**
     * Initialize all actions
     *
     * @return void
     */
    public function initializeAction()
    {
        if ($this->arguments->hasArgument('filters')) {
            $filters = $this->request->hasArgument('filters') ? $this->request->getArgument('filters') : [];

            if ((new Typo3Version())->getMajorVersion() < 11) {
                $this->request->setArgument('filters', $filters);
            } else {
                $this->request = $this->request->withArgument('filters', $filters);
            }

            $filtersArgument = $this->arguments->getArgument('filters');
            $filtersArgument->getPropertyMappingConfiguration()
                ->allowAllProperties()
                ->forProperty('*')
                    ->allowAllProperties();
        }
    }

    /**
     * Main overview action
     *
     * @param Filters $filters
     * @param int $currentPageNumber
     */
    public function indexAction(Filters $filters, int $currentPageNumber = 1): ResponseInterface
    {
        $entries = $this->formLogEntryRepository->findAllFiltered($filters);
        $paginator = new QueryResultPaginator($entries, $currentPageNumber);
        /** @var UriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        $this->view->assignMultiple([
            'entries' => $paginator->getPaginatedItems(),
            'entriesCount' => count($entries),
            'filters' => $filters,
            'pagination' => new SimplePagination($paginator),
            'currentPageNumber' => $currentPageNumber,
            'dateFormat' => $this->settings['dateTimeFormat'] ?: \DateTime::W3C,
            'isoDateFormat' => \DateTime::W3C,
            'daterangepickerTranslations' => $this->prepareDaterangepickerTranslations(),
            'inlineSettings' => [
                'formlog' => [
                    'suggestUri' => (string)$uriBuilder->buildUriFromRoute('ajax_formlog_suggest'),
                    'language' => $GLOBALS['BE_USER']->user['lang'],
                    'timeZone' => date_default_timezone_get(),
                ],
            ],
        ]);

        GeneralUtility::makeInstance(PageRenderer::class)->addRequireJsConfiguration([
            'paths' => [
                'TYPO3/CMS/Formlog/moment' => 'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment-with-locales.min',
            ],
        ]);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * Export CSV of form log entries
     *
     * @param Filters $filters
     */
    public function exportAction(Filters $filters): ResponseInterface
    {
        $export = GeneralUtility::makeInstance($this->viewFormatToExportMap[$this->request->getFormat()]);

        $now = new \DateTime();
        $fileBasename = sprintf('formlog-%s', $now->format('Y-m-d-H-i-s'));
        $logEntries = $this->formLogEntryRepository->findAllFiltered($filters);

        $export->setConfiguration([
            'columns' => $this->settings['export']['columns'],
            'dateTimeFormat' => $this->settings['dateTimeFormat'],
            'fileBasename' => $fileBasename,
        ]);

        $export->dump($logEntries);

        return $this->responseFactory->createResponse();
    }

    /**
     * Prepare localized daterangepicker labels
     *
     * @return array
     */
    protected function prepareDaterangepickerTranslations(): array
    {
        $translationIdentifiers = [
            'labels' => [
                'applyButtonTitle',
                'cancelButtonTitle',
                'startLabel',
                'endLabel',
            ],
            'ranges' => [
                'last30days',
                'lastYear',
                'other',
            ],
            'periods' => [
                'day',
                'week',
                'month',
                'quarter',
                'year',
            ],
        ];
        $translations = [];

        foreach ($translationIdentifiers as $section => $identifiers) {
            foreach ($identifiers as $identifier) {
                $translations[$section][$identifier] = LocalizationUtility::translate('formlog.daterangepicker.' . $section . '.' . $identifier, 'Formlog');
            }
        }

        return $translations;
    }
}
