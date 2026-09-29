/* EB Crédito Rural — bloco "Botão Área do Cliente" (ebcr/client-area-button). Sem etapa de build: usa os globais do editor. */
(function (wp) {
  'use strict';
  if (!wp || !wp.blocks || !wp.element) { return; }
  var el = wp.element.createElement;
  var be = wp.blockEditor || wp.editor;
  var c = wp.components;
  var SSR = wp.serverSideRender;
  var __ = (wp.i18n && wp.i18n.__) ? wp.i18n.__ : function (s) { return s; };
  var styles = [
    { label: __('Principal (cor da marca)', 'eb-credito-rural'), value: 'primary' },
    { label: __('Contorno', 'eb-credito-rural'), value: 'outline' },
    { label: __('Discreto (sem borda)', 'eb-credito-rural'), value: 'ghost' },
    { label: __('Link', 'eb-credito-rural'), value: 'link' }
  ];
  wp.blocks.registerBlockType('ebcr/client-area-button', {
    apiVersion: 3,
    title: __('Botão Área do Cliente', 'eb-credito-rural'),
    description: __('Botão que leva à área do cliente (portal). Mostra o primeiro nome e "Minha área" para quem está conectado.', 'eb-credito-rural'),
    category: 'widgets',
    icon: 'admin-users',
    keywords: ['cliente', 'login', 'portal'],
    attributes: {
      label: { type: 'string', default: '' },
      labelLogged: { type: 'string', default: '' },
      buttonStyle: { type: 'string', default: 'primary' },
      showIcon: { type: 'boolean', default: true }
    },
    supports: { html: false, className: true, align: ['left', 'center', 'right'] },
    edit: function (props) {
      var a = props.attributes;
      var set = function (k) { return function (v) { var o = {}; o[k] = v; props.setAttributes(o); }; };
      var blockProps = be && be.useBlockProps ? be.useBlockProps() : {};
      return el(wp.element.Fragment, null,
        el(be.InspectorControls, null,
          el(c.PanelBody, { title: __('Botão', 'eb-credito-rural'), initialOpen: true },
            el(c.TextControl, { label: __('Texto para visitantes', 'eb-credito-rural'), help: __('Vazio = "Área do Cliente" (ou o texto definido nas configurações).', 'eb-credito-rural'), value: a.label, onChange: set('label') }),
            el(c.TextControl, { label: __('Texto para quem está conectado', 'eb-credito-rural'), help: __('Vazio = "Minha área". O primeiro nome do usuário aparece antes.', 'eb-credito-rural'), value: a.labelLogged, onChange: set('labelLogged') }),
            el(c.SelectControl, { label: __('Estilo', 'eb-credito-rural'), value: a.buttonStyle, options: styles, onChange: set('buttonStyle') }),
            el(c.ToggleControl, { label: __('Mostrar ícone', 'eb-credito-rural'), checked: !!a.showIcon, onChange: set('showIcon') })
          )
        ),
        el('div', blockProps, SSR ? el(SSR, { block: 'ebcr/client-area-button', attributes: a }) : el('span', { className: 'ebcr-ca-btn ebcr-ca-btn--primary' }, a.label || __('Área do Cliente', 'eb-credito-rural')))
      );
    },
    save: function () { return null; }
  });
})(window.wp);
