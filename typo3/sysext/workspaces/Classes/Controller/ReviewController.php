<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Workspaces\Controller;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Versioning\VersionState;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Workspaces\Service\AdditionalColumnService;
use TYPO3\CMS\Workspaces\Service\AdditionalResourceService;
use TYPO3\CMS\Workspaces\Service\WorkspaceService;

/**
 * @internal This is a specific Backend Controller implementation and is not considered part of the Public TYPO3 API.
 */
class ReviewController extends ActionController
{
    /**
     * @var string
     */
    protected $defaultViewObjectName = BackendTemplateView::class;

    /**
     * @var BackendTemplateView
     */
    protected $view;

    /**
     * @var PageRenderer
     */
    protected $pageRenderer;

    /**
     * @var int
     */
    protected $pageId;

    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     */
    protected function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);
        $this->registerButtons();
        $this->view->getModuleTemplate()->setFlashMessageQueue($this->controllerContext->getFlashMessageQueue());
    }

    /**
     * Registers the DocHeader buttons
     */
    protected function registerButtons()
    {
        $buttonBar = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();
        $currentRequest = $this->request;
        $moduleName = $currentRequest->getPluginName();
        $getVars = $this->request->getArguments();
        $extensionName = $currentRequest->getControllerExtensionName();
        if (count($getVars) === 0) {
            $modulePrefix = strtolower('tx_' . $extensionName . '_' . $moduleName);
            $getVars = ['id', 'route', $modulePrefix];
        }
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setModuleName($moduleName)
            ->setGetVariables($getVars);
        $buttonBar->addButton($shortcutButton);
    }

    /**
     * Initializes the controller before invoking an action method.
     */
    protected function initializeAction()
    {
        $this->pageRenderer = $this->getPageRenderer();
        // @todo Evaluate how the (int) typecast can be used with Extbase validators/filters
        $this->pageId = (int)GeneralUtility::_GP('id');
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $lang = $this->getLanguageService();
        $icons = [
            'language' => $iconFactory->getIcon('flags-multiple', Icon::SIZE_SMALL)->render(),
            'integrity' => $iconFactory->getIcon('status-dialog-information', Icon::SIZE_SMALL)->render(),
            'success' => $iconFactory->getIcon('status-dialog-ok', Icon::SIZE_SMALL)->render(),
            'info' => $iconFactory->getIcon('status-dialog-information', Icon::SIZE_SMALL)->render(),
            'warning' => $iconFactory->getIcon('status-dialog-warning', Icon::SIZE_SMALL)->render(),
            'error' => $iconFactory->getIcon('status-dialog-error', Icon::SIZE_SMALL)->render()
        ];
        $this->pageRenderer->addInlineSetting('Workspaces', 'icons', $icons);
        $this->pageRenderer->addInlineSetting('Workspaces', 'id', $this->pageId);
        $this->pageRenderer->addInlineSetting('Workspaces', 'depth', $this->pageId === 0 ? 999 : 1);
        $this->pageRenderer->addInlineSetting('Workspaces', 'language', $this->getLanguageSelection());
        $this->pageRenderer->addInlineLanguageLabelArray([
            'title' => $lang->getLL('title'),
            'path' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.path'),
            'table' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.table'),
            'depth' => $lang->sL('LLL:EXT:beuser/Resources/Private/Language/locallang_mod_permission.xlf:Depth'),
            'depth_0' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_0'),
            'depth_1' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_1'),
            'depth_2' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_2'),
            'depth_3' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_3'),
            'depth_4' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_4'),
            'depth_infi' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_infi')
        ]);
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:workspaces/Resources/Private/Language/locallang.xlf');
        $states = $this->getBackendUser()->uc['moduleData']['Workspaces']['States'];
        $this->pageRenderer->addInlineSetting('Workspaces', 'States', $states);

        foreach ($this->getAdditionalResourceService()->getLocalizationResources() as $localizationResource) {
            $this->pageRenderer->addInlineLanguageLabelFile($localizationResource);
        }
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Workspaces/Backend');
        $this->pageRenderer->addInlineSetting('FormEngine', 'moduleUrl', (string)$uriBuilder->buildUriFromRoute('record_edit'));
        $this->pageRenderer->addInlineSetting('RecordHistory', 'moduleUrl', (string)$uriBuilder->buildUriFromRoute('record_history'));
        $this->pageRenderer->addInlineSetting('Workspaces', 'id', (int)GeneralUtility::_GP('id'));

        $this->assignExtensionSettings();
    }

    /**
     * Renders the review module user dependent with all workspaces.
     * The module will show all records of one workspace.
     */
    public function indexAction()
    {
        $backendUser = $this->getBackendUser();
        $moduleTemplate = $this->view->getModuleTemplate();

        if (GeneralUtility::_GP('id')) {
            $pageRecord = BackendUtility::getRecord('pages', GeneralUtility::_GP('id'));
            if ($pageRecord) {
                $moduleTemplate->getDocHeaderComponent()->setMetaInformation($pageRecord);
                $this->view->assign('pageTitle', BackendUtility::getRecordTitle('pages', $pageRecord));
            }
        }
        $wsList = GeneralUtility::makeInstance(WorkspaceService::class)->getAvailableWorkspaces();
        $customWorkspaceExists = $this->customWorkspaceExists($wsList);
        $activeWorkspace = $backendUser->workspace;
        $performWorkspaceSwitch = false;
        if ((string)GeneralUtility::_GP('workspace') !== '') {
            $switchWs = (int)GeneralUtility::_GP('workspace');
            if (array_key_exists($switchWs, $wsList) && $activeWorkspace != $switchWs) {
                $activeWorkspace = $switchWs;
                $backendUser->setWorkspace($activeWorkspace);
                $performWorkspaceSwitch = true;
                BackendUtility::setUpdateSignal('updatePageTree');
            }
        }
        $this->pageRenderer->addInlineSetting('Workspaces', 'isLiveWorkspace', (int)$backendUser->workspace === 0);
        $this->pageRenderer->addInlineSetting('Workspaces', 'workspaceTabs', $this->prepareWorkspaceTabs($wsList, $activeWorkspace));
        $this->pageRenderer->addInlineSetting('Workspaces', 'activeWorkspaceId', $activeWorkspace);
        $workspaceIsAccessible = $backendUser->workspace !== WorkspaceService::LIVE_WORKSPACE_ID;
        $this->view->assignMultiple([
            'isAdmin' => $backendUser->isAdmin(),
            'customWorkspaceExists' => $customWorkspaceExists,
            'showGrid' => $workspaceIsAccessible,
            'showLegend' => $workspaceIsAccessible,
            'pageUid' => (int)GeneralUtility::_GP('id'),
            'performWorkspaceSwitch' => $performWorkspaceSwitch,
            'workspaceList' => $this->prepareWorkspaceTabs($wsList, $activeWorkspace),
            'activeWorkspaceUid' => $activeWorkspace,
            'activeWorkspaceTitle' => WorkspaceService::getWorkspaceTitle($activeWorkspace),
        ]);

        if ($this->canCreatePreviewLink((int)GeneralUtility::_GP('id'), (int)$activeWorkspace)) {
            $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
            $iconFactory = $moduleTemplate->getIconFactory();
            $showButton = $buttonBar->makeLinkButton()
                ->setHref('#')
                ->setClasses('t3js-preview-link')
                ->setShowLabelText(true)
                ->setTitle($this->getLanguageService()->sL('LLL:EXT:workspaces/Resources/Private/Language/locallang.xlf:tooltip.generatePagePreview'))
                ->setIcon($iconFactory->getIcon('actions-version-workspaces-preview-link', Icon::SIZE_SMALL));
            $buttonBar->addButton($showButton);
        }
    }

    /**
     * Prepares available workspace tabs.
     *
     * @param array $workspaceList
     * @param int $activeWorkspace
     * @return array
     */
    protected function prepareWorkspaceTabs(array $workspaceList, int $activeWorkspace)
    {
        $tabs = [];

        if ($activeWorkspace !== WorkspaceService::LIVE_WORKSPACE_ID) {
            $tabs[] = [
                'title' => $workspaceList[$activeWorkspace],
                'itemId' => 'workspace-' . $activeWorkspace,
                'workspaceId' => $activeWorkspace,
                'triggerUrl' => $this->getModuleUri($activeWorkspace),
            ];
        }

        foreach ($workspaceList as $workspaceId => $workspaceTitle) {
            if ($workspaceId === $activeWorkspace
                || $workspaceId === WorkspaceService::LIVE_WORKSPACE_ID
            ) {
                continue;
            }
            $tabs[] = [
                'title' => $workspaceTitle,
                'itemId' => 'workspace-' . $workspaceId,
                'workspaceId' => $workspaceId,
                'triggerUrl' => $this->getModuleUri($workspaceId),
            ];
        }

        return $tabs;
    }

    /**
     * Gets the module URI.
     *
     * @param int $workspaceId
     * @return string
     */
    protected function getModuleUri(int $workspaceId): string
    {
        $parameters = [
            'id' => $this->pageId,
            'workspace' => $workspaceId,
        ];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        return (string)$uriBuilder->buildUriFromRoute('web_WorkspacesWorkspaces', $parameters);
    }

    /**
     * Assigns additional Workspace settings to TYPO3.settings.Workspaces.extension
     */
    protected function assignExtensionSettings()
    {
        $extension = [
            'AdditionalColumn' => [
                'Definition' => [],
                'Handler' => [],
            ],
        ];

        $extension['AdditionalColumn']['Definition'] = $this->getAdditionalColumnService()->getDefinition();
        $extension['AdditionalColumn']['Handler'] = $this->getAdditionalColumnService()->getHandler();
        $this->pageRenderer->addInlineSetting('Workspaces', 'extension', $extension);
    }

    /**
     * Determine whether this page for the current
     *
     * @param int $pageUid
     * @param int $workspaceUid
     * @return bool
     */
    protected function canCreatePreviewLink(int $pageUid, int $workspaceUid): bool
    {
        if ($pageUid > 0 && $workspaceUid > 0) {
            $pageRecord = BackendUtility::getRecord('pages', $pageUid);
            BackendUtility::workspaceOL('pages', $pageRecord, $workspaceUid);
            if (VersionState::cast($pageRecord['t3ver_state'])->equals(VersionState::DELETE_PLACEHOLDER)) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Gets the selected language.
     *
     * @return string
     */
    protected function getLanguageSelection(): string
    {
        $language = 'all';
        $backendUser = $this->getBackendUser();
        if (isset($backendUser->uc['moduleData']['Workspaces'][$backendUser->workspace]['language'])) {
            $language = $backendUser->uc['moduleData']['Workspaces'][$backendUser->workspace]['language'];
        }
        return $language;
    }

    /**
     * Returns true if at least one custom workspace next to live workspace exists.
     *
     * @param array $workspaceList
     * @return bool
     */
    protected function customWorkspaceExists(array $workspaceList): bool
    {
        foreach (array_keys($workspaceList) as $workspaceId) {
            if ($workspaceId > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return AdditionalColumnService
     */
    protected function getAdditionalColumnService(): AdditionalColumnService
    {
        return $this->objectManager->get(AdditionalColumnService::class);
    }

    /**
     * @return AdditionalResourceService
     */
    protected function getAdditionalResourceService(): AdditionalResourceService
    {
        return $this->objectManager->get(AdditionalResourceService::class);
    }

    /**
     * @return PageRenderer
     */
    protected function getPageRenderer(): PageRenderer
    {
        return GeneralUtility::makeInstance(PageRenderer::class);
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
