import metadata from '../../blocks/sidebar-search/block.json';
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    TextareaControl,
    ToggleControl,
    __experimentalVStack as VStack,
    Notice,
    Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useMemo, useRef, useState, RawHTML } from '@wordpress/element';
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

const HEADING_LEVEL_OPTIONS = [
    { value: 'h2', label: __('Titre 2', 'sidebar-jlg') },
    { value: 'h3', label: __('Titre 3', 'sidebar-jlg') },
    { value: 'h4', label: __('Titre 4', 'sidebar-jlg') },
    { value: 'h5', label: __('Titre 5', 'sidebar-jlg') },
    { value: 'h6', label: __('Titre 6', 'sidebar-jlg') },
];

const localizedDefaults = window.SidebarJlgSearchBlock?.defaults ?? {};
const blockName = window.SidebarJlgSearchBlock?.blockName ?? metadata.name;

const parseServerPreviewMarkup = (markup) => {
    if (typeof markup !== 'string') {
        return { markup: '', hasContent: false };
    }

    const trimmed = markup.trim();

    if (trimmed === '') {
        return { markup: '', hasContent: false };
    }

    if (typeof window === 'undefined' || !window.document?.createElement) {
        return { markup: trimmed, hasContent: true };
    }

    try {
        const wrapper = window.document.createElement('div');
        wrapper.innerHTML = trimmed;
        const container = wrapper.querySelector('.sidebar-search');
        const target = container ?? wrapper;

        if (typeof target.querySelectorAll === 'function') {
            target.querySelectorAll('.sidebar-search__heading, .sidebar-search__description').forEach((node) => {
                node.parentNode?.removeChild(node);
            });
        }

        const innerMarkup = container ? container.innerHTML.trim() : target.innerHTML.trim();
        const hasElementChildren = 'children' in target && target.children.length > 0;
        const textContent = 'textContent' in target && typeof target.textContent === 'string'
            ? target.textContent.trim()
            : '';

        return {
            markup: innerMarkup,
            hasContent: innerMarkup !== '' || textContent !== '' || hasElementChildren,
        };
    } catch (error) {
        return { markup: trimmed, hasContent: true };
    }
};

registerBlockType(metadata, {
    edit({ attributes, setAttributes }) {
        const initRef = useRef(false);
        const requestRef = useRef(0);
        const [renderState, setRenderState] = useState({
            status: 'idle',
            rendered: '',
            errorMessage: null,
        });

        useEffect(() => {
            if (initRef.current) {
                return;
            }

            initRef.current = true;

            const initialValues = {};
            [
                'enable_search',
                'search_method',
                'search_alignment',
                'search_shortcode',
                'heading',
                'description',
                'heading_level',
            ].forEach((key) => {
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
            heading: attributes.heading ?? '',
            description: attributes.description ?? '',
            heading_level: attributes.heading_level ?? 'h2',
        }), [
            attributes.enable_search,
            attributes.search_method,
            attributes.search_alignment,
            attributes.search_shortcode,
            attributes.heading,
            attributes.description,
            attributes.heading_level,
        ]);

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

        const parsedServerPreview = useMemo(
            () => parseServerPreviewMarkup(renderState.rendered),
            [renderState.rendered]
        );

        useEffect(() => {
            if (!normalizedAttributes.enable_search) {
                setRenderState({
                    status: 'idle',
                    rendered: '',
                    errorMessage: null,
                });
                return;
            }

            const currentRequest = requestRef.current + 1;
            requestRef.current = currentRequest;

            setRenderState((prevState) => ({
                ...prevState,
                status: 'loading',
                errorMessage: null,
            }));

            const path = `/wp/v2/block-renderer/${ encodeURIComponent(blockName) }?context=edit`;

            apiFetch({
                path,
                method: 'POST',
                data: {
                    attributes: {
                        enable_search: normalizedAttributes.enable_search,
                        search_method: normalizedAttributes.search_method,
                        search_alignment: normalizedAttributes.search_alignment,
                        search_shortcode: normalizedAttributes.search_shortcode,
                        heading: normalizedAttributes.heading,
                        description: normalizedAttributes.description,
                        heading_level: normalizedAttributes.heading_level,
                    },
                },
            })
                .then((response) => {
                    if (requestRef.current !== currentRequest) {
                        return;
                    }

                    const rendered = typeof response?.rendered === 'string' ? response.rendered : '';

                    setRenderState({
                        status: 'success',
                        rendered,
                        errorMessage: null,
                    });
                })
                .catch((error) => {
                    if (requestRef.current !== currentRequest) {
                        return;
                    }

                    setRenderState((prevState) => ({
                        ...prevState,
                        status: 'error',
                        errorMessage:
                            error?.message ?? __('Une erreur est survenue lors du chargement de l’aperçu.', 'sidebar-jlg'),
                    }));
                });
        }, [
            normalizedAttributes.enable_search,
            normalizedAttributes.search_method,
            normalizedAttributes.search_alignment,
            normalizedAttributes.search_shortcode,
            blockName,
        ]);

        const renderPreviewContent = () => {
            const containerClassNames = [
                'sidebar-search',
                alignmentClass,
                'sidebar-search--scheme-light',
            ]
                .filter(Boolean)
                .join(' ');

            const containerProps = {
                className: containerClassNames,
                'data-sidebar-search-align': normalizedAttributes.search_alignment,
                'data-sidebar-search-scheme': 'auto',
                style: { '--sidebar-search-alignment': normalizedAttributes.search_alignment },
            };

            const isLoading = renderState.status === 'loading';

            const loadingContent = (
                <div className="sidebar-search__loading">
                    <Spinner />
                </div>
            );

            const headingLevelOption = HEADING_LEVEL_OPTIONS.find(
                ({ value }) => value === normalizedAttributes.heading_level
            );
            const headingTagName = headingLevelOption?.value ?? 'h2';

            const emptyMessage = (
                <div className="sidebar-search__placeholder">
                    { __('Aucun contenu n’a été retourné par le serveur pour cette configuration.', 'sidebar-jlg') }
                </div>
            );

            const disabledNotice = !normalizedAttributes.enable_search ? (
                <Notice status="info" isDismissible={ false }>
                    { __('La recherche est désactivée pour cette instance.', 'sidebar-jlg') }
                </Notice>
            ) : null;

            let bodyContent;

            if (disabledNotice) {
                bodyContent = disabledNotice;
            } else if (parsedServerPreview.hasContent) {
                bodyContent = (
                    <div className="sidebar-search__render" aria-live="polite">
                        <RawHTML>{ parsedServerPreview.markup }</RawHTML>
                        { isLoading && loadingContent }
                    </div>
                );
            } else if (isLoading) {
                bodyContent = loadingContent;
            } else {
                bodyContent = emptyMessage;
            }

            return (
                <>
                    { renderState.errorMessage && (
                        <Notice status="error" isDismissible={ false }>
                            { renderState.errorMessage }
                        </Notice>
                    ) }
                    <div { ...containerProps } aria-live="polite">
                        <RichText
                            tagName={ headingTagName }
                            className="sidebar-search__heading"
                            value={ normalizedAttributes.heading }
                            placeholder={ __('Ajouter un titre…', 'sidebar-jlg') }
                            allowedFormats={ [ 'core/bold', 'core/italic', 'core/link', 'core/strikethrough' ] }
                            onChange={(value) => setAttributes({ heading: value }) }
                        />
                        <RichText
                            tagName="div"
                            className="sidebar-search__description"
                            value={ normalizedAttributes.description }
                            placeholder={ __('Ajouter une description…', 'sidebar-jlg') }
                            onChange={(value) => setAttributes({ description: value }) }
                            allowedFormats={ [ 'core/bold', 'core/italic', 'core/link', 'core/strikethrough', 'core/underline' ] }
                            multiline="p"
                        />
                        { bodyContent }
                    </div>
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
                            <SelectControl
                                label={ __('Niveau de titre', 'sidebar-jlg') }
                                value={ normalizedAttributes.heading_level }
                                options={ HEADING_LEVEL_OPTIONS }
                                onChange={(value) => setAttributes({ heading_level: value }) }
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
