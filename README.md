# Local Rank — SEO Programático

Plugin de WordPress para o time executar SEO programático sem snippet PHP por cliente. A aba **Roteiro** define a arquitetura, mostra a cobertura e entrega a chave única da importação. O plugin publica as URLs, o contexto, os shortcodes, as Dynamic Tags, o mapa, o breadcrumb e o schema. ACF Pro, Elementor, WP All Import e Yoast continuam nas etapas deles.

Descrição curta, para a página do repositório:

> Roteiro e lógica de SEO programático no WordPress. Arquitetura de 2 ou 3 níveis, URLs, shortcodes, Dynamic Tags e schema, com chave única para o WP All Import. Não substitui ACF, Elementor, WP All Import nem Yoast.

O passo a passo de instalação, das quatro abas e de um cliente novo está no [GUIA.md](GUIA.md). O processo completo segue o [guia de SEO programático](https://app.notion.com/p/4095fd8730d88064bef7e20edf85ef10).

A tela no wp-admin usa a marca da Local Rank: o logo branco e verde no azul `#0e1d38`, Sora nos títulos, Manrope no texto e o verde `#a3d115` nas ações. O plugin não registra field group; só preenche as opções do select de cidade a partir do catálogo. O prefixo `phf` e o catálogo da Prime House Flooring são o exemplo de um cliente. O próximo cliente ganha o próprio prefixo, os próprios níveis e o próprio catálogo.

## O que entra em cada página

| Peça | Onde vive |
| --- | --- |
| Arquitetura, URL de exemplo, conta de páginas e chave única | Aba Roteiro deste plugin |
| CPT, taxonomia e campos | Etapa 02, ACF Pro. O plugin cria o CPT se ele ainda não existir |
| URL, contexto, componentes e schema | Este plugin |
| Template visual | Etapa 05, Elementor |
| Textos e planilha | Etapas 04, 06 e 07, WP All Import |

## Modelos de URL

O CPT é plano. A hierarquia pública sai dos campos, do catálogo e, quando existe, de um prefixo literal. A página estática não vira post pai.

| Modelo | URLs | Breadcrumb |
| --- | --- | --- |
| Cidade e serviço | `/{cidade}/` e `/{cidade}/{servico}/` | Home, a página de áreas, a cidade e o serviço. É o modelo da Prime House. `/service-areas/` não é um nível. |
| Filha de uma página estática | `/services/{servico}/` e `/services/{servico}/{cidade}/` | Home, Services, o serviço e a cidade. `/services/` continua uma página comum. |
| Estado, cidade e serviço | `/{estado}/`, `/{estado}/{cidade}/` e `/{estado}/{cidade}/{servico}/` | Sobe um nível de cada vez. |

A aba Roteiro aplica um desses modelos. Se a página estática tiver outro slug, edite `prefix` e o caminho do `trail` no JSON da aba Projeto e mantenha esse slug na lista de reservados. Um `trail` vazio preserva o breadcrumb antigo: Home, página de áreas e cidade.

A cobertura é a conta do roteiro. Dois níveis de cidade e serviço viram cidades + cidades × serviços. Não invente endereço, filial ou prova. Página localizada publica schema `Service` com `areaServed`. O schema só sai quando a página tem cidade e estado.

A coluna `unique_key` da planilha é o identificador do WP All Import. Cidade: `{city_slug}__city`. Serviço: `{city_slug}__service__{service_slug}`. A mesma chave na reimportação atualiza o post. Uma chave nova cria outro.

Cidade atendida não vira endereço de filial. O schema localizado é um `Service` com `areaServed`. O endereço postal, quando existe, sai só na entidade da empresa e só fora das páginas locais.

## Instalar

1. Copie esta pasta para `wp-content/plugins/local-rank-programmatic-seo`.
2. Ative **Local Rank — SEO Programático**.
3. Abra **SEO programático** no admin. A primeira aba é o Roteiro.
4. Para um cliente novo, escolha o modelo, preencha prefixo, campos e catálogos.
5. Para repetir a Prime House, importe `examples/prime-house-flooring.json` e desative os snippets PHP listados abaixo antes de publicar. O JSON traz o catálogo fechado que está no ar: as cidades do tri-state, os seis serviços com hero, galeria e problems, os subcampos reais do FAQ e os IDs dos templates 2172 e 2173.

O ambiente precisa de WordPress 6.2+, PHP 7.4+, ACF Pro para os campos e Elementor (com Elementor Pro no Theme Builder) para Dynamic Tags e Saved Templates. Yoast é opcional; o canonical e, se você ligar, o BreadcrumbList conversam com ele.

Se o CPT e a taxonomia já existem, o módulo de estrutura não registra de novo. Ele só garante os termos e copia o select de nível para a taxonomia.

## Projeto

O prefixo vira o nome público. Com `phf`, os templates atuais seguem usando `[phf_city_state]` e a Dynamic Tag `phf-location-url`. Com `lrps`, os mesmos controles nascem como `[lrps_city_state]` e `lrps-location-url`.

Os slugs de cidade e serviço precisam ser os mesmos no catálogo, no ACF, na URL e na planilha. Slug fora do catálogo não gera rota. Slug reservado (`services`, `service-areas`, `blog` e a lista da tela) também não.

IDs de Saved Template pertencem à instalação. `gallery_template_id: 0` devolve vazio. O plugin não preenche a lacuna com a galeria de outro serviço.

Há um atalho por código em `examples/mu-plugin-config.php`. O filtro `lrps_config` passa na frente do que está salvo no banco.

## Ligações da etapa 05

A aba **Referência** lista os shortcodes e as Dynamic Tags do projeto atual. Os tokens `{city_state}` e `{service_name}` existem porque um shortcode não pode ficar dentro do atributo de outro: o WordPress fecha no primeiro `]`.

O mapa oficial é a URL validada no `src` do iframe, pela tag `prefix-map-embed-url` ou pelo shortcode `[prefix_map_embed_url]`. Só entram URLs `https` de `google.com/maps/embed` ou `www.google.com/maps/embed`.

`[prefix_schema_city]` fica no final do template de cidade. `[prefix_schema_service]` fica no final do template de serviço. Cada um imprime um único JSON-LD.

A condição de exibição do Elementor continua escolhendo o template. A opção de priorizar template só reordena um candidato que o Elementor já tenha listado.

## Diagnóstico

A aba **Diagnóstico** confere CPT, termos, catálogo, regras, colisão com páginas estáticas, combinação duplicada, templates informados e endereço da empresa. **Sincronizar níveis** copia o select para a taxonomia sem mexer no conteúdo.

Antes de importar o lote, o diagnóstico precisa estar sem bloqueio. Uma combinação duplicada de cidade, nível e serviço não resolve: a URL cai em 404 em vez de escolher um dos registros.

## O que substitui no WPCode da Prime House

O site de referência usa snippets PHP no WPCode. Com o prefixo `phf` e o JSON de exemplo importado, o plugin assume estes:

| Snippet | O que o plugin faz no lugar |
| --- | --- |
| PHF \| SEO Programmatic \| Hierarchical URLs | Módulo de URLs. `/{cidade}/` e `/{cidade}/{servico}/`, só com slug do catálogo. |
| PHF \| Programmatic SEO Core | Catálogo, contexto, mapa, breadcrumb, schema e Dynamic Tags `phf-*`. |
| PHF \| City Shortcodes | `[phf_city]`, `[phf_city_state]`, `[phf_service_name]`, hero, mapa e `[phf_schema_city]` / `[phf_schema_service]`. |
| PHF \| FAQ Item Shortcodes | `[phf_faq_question index="1"]` e `[phf_faq_answer index="1"]`. O repeater real usa `phf_faq_question` e `phf_faq_answer`. |
| PHF \| Dynamic Problems Section | `[phf_problems_section]`, pelo `problems_template_id` do serviço. |
| PHF \| Dynamic Service Gallery | `[phf_service_gallery]`. Laminate continua com ID 0 e não herda outra galeria. |
| Prime House Design System – Section Label | `[section_label]` com a classe no próprio título. Na Prime House a classe é `ph-section-label`, que o CSS do design system já estiliza. |
| PHF \| HTML Sitemap | `[phf_html_sitemap]` na página `/sitemap/`. |
| Local Schema Markup | JSON-LD da empresa nas páginas que não são location page. |
| PHF \| Kadence Location Header | Header transparente, título oculto e layout full-width nas location pages. |
| PHF \| SEO Programmatic \| CPT Taxonomy ACF | O CPT e a taxonomia, se ainda não existirem, a cópia do select para o termo e as choices de cidade. O field group em si continua no ACF; o registro em PHP desse snippet já estava desligado. |

Desative esses snippets PHP antes de deixar o plugin publicado. Dois registros da mesma URL ou do mesmo shortcode brigam entre si.

Podem ficar ativos, porque não são a lógica da etapa 03: o CSS de breadcrumb, o CSS global e do `ph-section-label`, o botão flutuante de ligação, o ajuste de espaçamento de heading e os snippets de rodapé do Kadence.

Os templates que já estão no Elementor não precisam ser refeitos. Eles chamam `[phf_city_state]`, `[phf_faq_question index="1"]`, `[section_label tag="h2" text="... {city_state}"]` e as Dynamic Tags `phf-location-url`, `phf-service-hero-image`, `phf-service-cover-image` e `phf-city-content`. O nome do controle de URL continua `service`.

## O que este plugin não faz

- Não cria o field group do ACF.
- Não desenha o template nem altera protótipos.
- Não importa a planilha. Isso continua no WP All Import, nas etapas 06 a 09.
- Não monta a página `/service-areas/`. O sitemap HTML é outra peça: a lista técnica em `/sitemap/`.
- Não liga FAQPage nem troca o BreadcrumbList do Yoast por padrão. As duas saídas são opcionais, porque duplicar schema é pior do que omitir.
- Não regrava as condições de exibição do Theme Builder. Na Prime House elas já apontam o template 2172 para cidade e o 2173 para serviço.

## Testes

Os testes cobrem caminho, rewrite, mapa, schema, catálogo e a importação do exemplo. Eles não sobem um WordPress.

```bash
composer install
composer exec phpunit
```

## Licença

GPL-2.0-or-later.
