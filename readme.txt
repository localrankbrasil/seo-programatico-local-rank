=== Local Rank — Programático ===
Contributors: localrankbrasil
Tags: seo, local seo, elementor, schema
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.2
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
3. Abra Programático. A aba Começar pede o cliente, o formato da URL, as cidades e os serviços. A aba Próximos passos mostra o que falta no ACF, no Elementor, no WP All Import e no Yoast.
4. Desative snippets antigos que registrem as mesmas rotas.
5. Use a aba Referência na hora de ligar os templates e a aba Diagnóstico antes da importação.

== Frequently Asked Questions ==

= O plugin cria os campos ACF? =

Não. O grupo continua sendo criado e editado no ACF Pro.

= Cada cliente precisa do próprio prefixo? =

Sim. O prefixo só nomeia os shortcodes daquele site. Troque também níveis, catálogos, rótulo do ofício e IDs de template. `examples/projeto.json` mostra o formato, sem dados de um cliente real.

= Por que uma cidade do catálogo retorna 404? =

A rota reconhece o catálogo, mas só publica a página se existir exatamente um registro daquele nível, cidade e serviço. Combinação duplicada também vira 404.

== Changelog ==

= 1.3.2 =
* A marca verde da Local Rank aparece no item Programático do menu do admin.

= 1.3.1 =
* Remove o prefixo e o catálogo de um cliente antigo. Cada site informa o próprio prefixo no formulário.

= 1.3.0 =
* A primeira tela é um formulário: cliente, formato da URL, cidades e serviços. O plugin monta a estrutura e a aba Próximos passos lista o que continua no ACF, no Elementor, no WP All Import e no Yoast.
* O menu passa a se chamar Programático.
* O logo branco e verde volta a aparecer no topo da tela.

= 1.2.2 =
* Inclui o guia de uso em GUIA.md, com a descrição do plugin e o passo a passo das quatro abas.

= 1.2.1 =
* A tela do plugin passa a usar a marca da Local Rank: logo, Sora, Manrope, azul profundo e verde.

= 1.2.0 =
* A aba Roteiro conduz as nove etapas e mostra URL de exemplo, cobertura, campos do ACF, CSV e o padrão de título do Yoast.
* Cada nível aceita um prefixo literal, para páginas programáticas filhas de uma página estática, e um breadcrumb próprio.
* Três modelos prontos: cidade e serviço, filha de Services, e estado + cidade + serviço.

= 1.1.0 =
* Alinha o exemplo de projeto ao catálogo, aos FAQs e aos shortcodes dos templates.
* O section_label imprime a classe no próprio título, para o design system existente continuar valendo.
* Sitemap HTML, header do Kadence nas location pages e escolhas de cidade no ACF passam a ser módulos do plugin.

= 1.0.0 =
* Primeira versão da etapa 03: URLs, contexto, componentes e schema configuráveis por cliente.
