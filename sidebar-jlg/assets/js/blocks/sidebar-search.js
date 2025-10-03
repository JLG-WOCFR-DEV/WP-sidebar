import metadata from '../../blocks/sidebar-search/block.json';
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    TextareaControl,
    ToggleControl,
    __experimentalVStack as VStack,
    Notice,
    Spinner,
} from '@wordpress/components';
import { useServerSideRender } from '@wordpress/server-side-render';
import { useEffect, useMemo, useRef, RawHTML } from '@wordpress/element';
import '../../css/sidebar-search-editor.scss';

const METHOD_OPTIONS = [
    { value: 'default', label: __('Formulaire de recherche WordPress', 'sidebar-jlg') },
    { value: 'shortcode', label: __('Shortcode personnalisé', 'sidebar-jlg') },
    { value: 'hook', label: __('Hook "jlg_sidebar_search_area"', 'sidebar-jlg') },
];

const ALIGNMENT_OPTIONS = [
    { value: 'flex-start', label: __('Alignement à gauche', 'sidebar-jlg') },
    { value: 'center', label: __('Alignement centré', 'sidebar-jlg') },
    { value: 'flex-end', label: __('Alignement à droite', 'sidebar-jlg') },
];

const localizedDefaults = window.SidebarJlgSearchBlock?.defaults ?? {};
const blockName = window.SidebarJlgSearchBlock?.blockName ?? metadata.name;

registerBlockType(metadata, {
    edit({ attributes, setAttributes }) {
        const initRef = useRef(false);
        const previousMarkupRef = useRef('');

        useEffect(() => {
            if (initRef.current) {
                return;
            }

            initRef.current = true;

            const initialValues = {};
            ['enable_search', 'search_method', 'search_alignment', 'search_shortcode'].forEach((key) => {
                if (attributes[key] === undefined && localizedDefaults[key] !== undefined) {
                    initialValues[key] = localizedDefaults[key];
                }
            });

            if (Object.keys(initialValues).length > 0) {
                setAttributes(initialValues);
            }
        }, [attributes, setAttributes]);

        const normalizedAttributes = useMemo(() => ({
            enable_search: attributes.enable_search ?? false,
            search_method: attributes.search_method ?? 'default',
            search_alignment: attributes.search_alignment ?? 'flex-start',
            search_shortcode: attributes.search_shortcode ?? '',
        }), [attributes.enable_search, attributes.search_method, attributes.search_alignment, attributes.search_shortcode]);

        const blockProps = useBlockProps({
            className: 'sidebar-jlg-search-block',
        });

        const alignmentClass = useMemo(() => {
            switch (normalizedAttributes.search_alignment) {
                case 'center':
                    return 'sidebar-search--align-center';
                case 'flex-end':
                    return 'sidebar-search--align-end';
                default:
                    return 'sidebar-search--align-start';
            }
        }, [normalizedAttributes.search_alignment]);

        const serverRequestArgs = useMemo(() => ({
            block: blockName,
            attributes: {
                enable_search: normalizedAttributes.enable_search,
                search_method: normalizedAttributes.search_method,
                search_alignment: normalizedAttributes.search_alignment,
                search_shortcode: normalizedAttributes.search_shortcode,
            },
            httpMethod: 'POST',
        }), [
            blockName,
            normalizedAttributes.enable_search,
            normalizedAttributes.search_method,
            normalizedAttributes.search_alignment,
            normalizedAttributes.search_shortcode,
        ]);

        const serverRender = useServerSideRender(serverRequestArgs);

        useEffect(() => {
            if (!normalizedAttributes.enable_search) {
                previousMarkupRef.current = '';
                return;
            }

            if (serverRender.status === 'success') {
                const rendered = typeof serverRender.content === 'string' ? serverRender.content : '';
                const trimmed = rendered.trim();

                if (trimmed !== '') {
                    previousMarkupRef.current = rendered;
                } else {
                    previousMarkupRef.current = '';
                }
            }
        }, [normalizedAttributes.enable_search, serverRender.status, serverRender.content]);

        const renderPreviewContent = () => {
            const containerProps = {
                className: `sidebar-search ${ alignmentClass }`,
                'data-sidebar-search-align': normalizedAttributes.search_alignment,
                style: { '--sidebar-search-alignment': normalizedAttributes.search_alignment },
            };

            if (!normalizedAttributes.enable_search) {
                return (
                    <div { ...containerProps }>
                        <Notice status="info" isDismissible={ false }>
                            { __('La recherche est désactivée pour cette instance.', 'sidebar-jlg') }
                        </Notice>
                    </div>
                );
            }

            const rawServerMarkup = typeof serverRender.content === 'string' ? serverRender.content : '';
            const trimmedServerMarkup = rawServerMarkup.trim();
            const storedMarkup = previousMarkupRef.current ?? '';
            const trimmedStoredMarkup = storedMarkup.trim();
            const isLoading = normalizedAttributes.enable_search
                && (serverRender.status === 'loading' || serverRender.status === 'idle');
            const hasImmediateMarkup = trimmedServerMarkup !== '';
            const shouldUseStoredMarkup = !hasImmediateMarkup && isLoading && trimmedStoredMarkup !== '';
            const markupToDisplay = hasImmediateMarkup
                ? rawServerMarkup
                : shouldUseStoredMarkup
                    ? storedMarkup
                    : '';
            const trimmedMarkupToDisplay = markupToDisplay.trim();
            let hasServerContent = trimmedMarkupToDisplay !== '';

            if (hasServerContent && typeof window !== 'undefined' && window.document) {
                const wrapper = window.document.createElement('div');
                wrapper.innerHTML = trimmedMarkupToDisplay;
                const serverContainer = wrapper.querySelector('.sidebar-search');
                const target = serverContainer ?? wrapper;
                const hasElementChildren = target.children.length > 0;
                const textContent = target.textContent ? target.textContent.trim() : '';
                hasServerContent = hasElementChildren || textContent !== '';
            }

            const loadingContent = (
                <div className="sidebar-search__loading">
                    <Spinner />
                </div>
            );

            const emptyMessage = (
                <div className="sidebar-search__placeholder">
                    { __('Aucun contenu n’a été retourné par le serveur pour cette configuration.', 'sidebar-jlg') }
                </div>
            );

            const errorMessage = serverRender.status === 'error'
                ? serverRender.error ?? __('Une erreur est survenue lors du chargement de l’aperçu.', 'sidebar-jlg')
                : null;

            return (
                <>
                    { errorMessage && (
                        <Notice status="error" isDismissible={ false }>
                            { errorMessage }
                        </Notice>
                    ) }
                    { trimmedMarkupToDisplay !== '' && hasServerContent ? (
                        <div className="sidebar-search__render" aria-live="polite">
                            <RawHTML>{ markupToDisplay }</RawHTML>
                            { isLoading && loadingContent }
                        </div>
                    ) : (
                        <div { ...containerProps } aria-live="polite">
                            { isLoading ? loadingContent : emptyMessage }
                        </div>
                    ) }
                </>
            );
        };

        return (
            <>
                <InspectorControls>
                    <PanelBody title={ __('Options de recherche', 'sidebar-jlg') } initialOpen>
                        <VStack spacing={ 3 }>
                            <ToggleControl
                                label={ __('Activer la recherche', 'sidebar-jlg') }
                                checked={ !!normalizedAttributes.enable_search }
                                onChange={(value) => setAttributes({ enable_search: value })}
                            />
                            <SelectControl
                                label={ __('Méthode de recherche', 'sidebar-jlg') }
                                value={ normalizedAttributes.search_method }
                                options={ METHOD_OPTIONS }
                                onChange={(value) => setAttributes({ search_method: value }) }
                            />
                            <SelectControl
                                label={ __('Alignement', 'sidebar-jlg') }
                                value={ normalizedAttributes.search_alignment }
                                options={ ALIGNMENT_OPTIONS }
                                onChange={(value) => setAttributes({ search_alignment: value }) }
                            />
                            { normalizedAttributes.search_method === 'shortcode' && (
                                <TextareaControl
                                    label={ __('Shortcode', 'sidebar-jlg') }
                                    help={ __('Le shortcode est exécuté côté front et doit retourner un formulaire de recherche.', 'sidebar-jlg') }
                                    value={ normalizedAttributes.search_shortcode }
                                    onChange={(value) => setAttributes({ search_shortcode: value }) }
                                />
                            ) }
                        </VStack>
                    </PanelBody>
                </InspectorControls>
                <div { ...blockProps } data-sidebar-search-block-name={ blockName }>
                    { renderPreviewContent() }
                </div>
            </>
        );
    },
    save() {
        return null;
    },
});
