=== Local Rank — SEO Programático ===
Contributors: localrankbrasil
Tags: seo, local seo, elementor, schema
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Roteiro e lógica de SEO programático no WordPress: arquitetura, URLs, shortcodes, Dynamic Tags e schema. Não substitui ACF, Elementor, WP All Import nem Yoast.

== Description ==

O plugin leva o time do modelo de URL até a página no ar sem snippet PHP por cliente. A aba Roteiro escolhe a arquitetura, mostra a conta de páginas e entrega a chave única do WP All Import. O guia de uso está em GUIA.md.

Ele transforma registros de um CPT em páginas localizadas. A hierarquia pública sai de campos e catálogos, não de posts aninhados.

* Roteiro com três modelos de URL: cidade e serviço, filha de uma página estática, ou estado, cidade e serviço.
* Permalinks limitados ao catálogo, com prefixo literal quando a página pai é estática.
* Chave única e cabeçalho de CSV para o WP All Import.
* Contexto estável dentro de Saved Templates do Elementor.
* Shortcodes e Dynamic Tags para texto, link, imagem, FAQ, mapa e breadcrumb.
* Problems e Gallery escolhidos pelo serviço, sem fallback para outro serviço.
* Schema `Service` com `areaServed`. Endereço postal só na entidade da empresa, fora das páginas locais.

Os campos continuam no ACF Pro. O layout continua no Elementor. Prefixo, catálogos e IDs mudam por cliente.

== Installation ==

1. Envie a pasta para `/wp-content/plugins/local-rank-programmatic-seo`.
2. Ative o plugin.
3. Abra SEO programático, comece pelo Roteiro e importe o JSON do cliente ou preencha o projeto na tela.
4. Desative snippets antigos que registrem as mesmas rotas.
5. Use a aba Referência na hora de ligar os templates e a aba Diagnóstico antes da importação.

== Frequently Asked Questions ==

= O plugin cria os campos ACF? =

Não. O grupo continua sendo criado e editado no ACF Pro.

= Posso usar em outro cliente além da Prime House? =

Sim. Troque prefixo, nomes de campo, níveis, catálogos, rótulo do ofício e IDs de template. O exemplo `examples/prime-house-flooring.json` mostra o formato.

= Por que uma cidade do catálogo retorna 404? =

A rota reconhece o catálogo, mas só publica a página se existir exatamente um registro daquele nível, cidade e serviço. Combinação duplicada também vira 404.

== Changelog ==

= 1.2.2 =
* Inclui o guia de uso em GUIA.md, com a descrição do plugin e o passo a passo das quatro abas.

= 1.2.1 =
* A tela do plugin passa a usar a marca da Local Rank: logo, Sora, Manrope, azul profundo e verde.

= 1.2.0 =
* A aba Roteiro conduz as nove etapas e mostra URL de exemplo, cobertura, campos do ACF, CSV e o padrão de título do Yoast.
* Cada nível aceita um prefixo literal, para páginas programáticas filhas de uma página estática, e um breadcrumb próprio.
* Três modelos prontos: cidade e serviço, filha de Services, e estado + cidade + serviço.

= 1.1.0 =
* Alinha o exemplo da Prime House ao catálogo, aos FAQs e aos shortcodes que já estão nos templates.
* O section_label imprime a classe no próprio título, para o design system existente continuar valendo.
* Sitemap HTML, header do Kadence nas location pages e escolhas de cidade no ACF passam a ser módulos do plugin.

= 1.0.0 =
* Primeira versão da etapa 03: URLs, contexto, componentes e schema configuráveis por cliente.
