# Guia de uso — Local Rank SEO Programático

Plugin de WordPress para o time montar SEO programático sem escrever PHP por cliente. Ele guarda a arquitetura, publica as URLs a partir do catálogo e entrega shortcodes, Dynamic Tags e schema. O layout continua no Elementor. Os campos continuam no ACF Pro. A planilha continua no WP All Import. O título continua no Yoast.

O prefixo `phf` e o arquivo `examples/prime-house-flooring.json` são o exemplo da Prime House Flooring. Cada cliente novo recebe o próprio prefixo, os próprios níveis e o próprio catálogo.

## O que o plugin resolve

- Escolhe o modelo de URL: dois níveis, três níveis, ou páginas programáticas filhas de uma página estática.
- Mostra a URL de exemplo, a conta de páginas e a chave única da importação.
- Cria o CPT e a taxonomia se eles ainda não existirem, e preenche o select de cidade com o catálogo.
- Resolve o permalink, o contexto dentro do Elementor, o mapa, o breadcrumb, Problems, Gallery e o schema `Service` com `areaServed`.

## O que continua fora dele

- O grupo de campos é criado na mão no ACF Pro. O plugin não registra field group.
- O desenho da página fica no Elementor.
- A importação da planilha fica no WP All Import.
- O título e a meta description ficam no Yoast.
- A página índice, como `/service-areas/` ou `/services/`, continua uma página comum. Ela entra na URL como prefixo e no breadcrumb como item estático. Ela não vira post pai.

## Requisitos

WordPress 6.2+, PHP 7.4+, ACF Pro e Elementor. Elementor Pro entra quando o template é do Theme Builder. Yoast e WP All Import Pro entram nas etapas deles. Kadence é opcional: o módulo de header só vale nesse tema.

## Instalar

1. Copie a pasta para `wp-content/plugins/local-rank-programmatic-seo`.
2. Ative **Local Rank — Programático**.
3. No admin, abra **Programático**. A aba **Começar** é um formulário: nome do cliente, ofício, prefixo, formato da URL, cidades e serviços. Ao salvar, o plugin cria as rotas, os shortcodes e o schema. A aba **Próximos passos** mostra o que ainda é feito no ACF, no Elementor, no WP All Import e no Yoast.

No site que já tem snippets PHP das mesmas URLs, desative esses snippets antes de publicar o plugin. Dois registros da mesma rota ou do mesmo shortcode brigam entre si. CSS de layout pode ficar.

## As quatro abas

| Aba | Para que serve |
| --- | --- |
| Começar | Formulário do cliente: formato da URL, cidades e serviços. Salvar cria a estrutura. |
| Próximos passos | Ver a cobertura, copiar os campos do ACF, copiar o CSV e marcar as nove etapas. |
| Projeto | Prefixo, catálogos, níveis, breadcrumb, schema, empresa, módulos e importação do JSON. |
| Referência | Shortcodes, Dynamic Tags e tokens do prefixo deste cliente, para colar no Elementor. |
| Diagnóstico | Conferir CPT, catálogo, rotas, página estática no mesmo slug, combinação duplicada e templates antes do lote. |

## Cliente novo, na ordem

### 1. Arquitetura

Na aba **Começar**, escolha o formato da URL. Ele define os níveis e o breadcrumb. Catálogo, prefixo e campos permanecem. A aba **Próximos passos** mostra o resultado.

| Modelo | Quando usar | URLs |
| --- | --- | --- |
| Cidade e serviço | A cidade é a primeira página e o serviço mora dentro dela. É o caso da Prime House. | `/{cidade}/` e `/{cidade}/{servico}/` |
| Filha de uma página estática | Existe uma página comum, como Services, e o programático nasce debaixo dela. | `/services/{servico}/` e `/services/{servico}/{cidade}/` |
| Estado, cidade e serviço | São três níveis públicos. | `/{estado}/`, `/{estado}/{cidade}/` e `/{estado}/{cidade}/{servico}/` |

Confira a URL de exemplo e a coluna **Páginas**. A conta é a cobertura real: cada nível multiplica só os catálogos que entram na URL. Dois níveis de cidade e serviço viram cidades + cidades × serviços.

Se a página estática não se chama `services`, escolha o segundo formato na aba **Começar** e escreva o nome e o endereço dessa página. O plugin reserva esse slug para uma cidade não capturar a página comum.

Sem `trail`, o breadcrumb antigo continua valendo: Home, a página de áreas e a cidade. Com `trail`, cada nível declara a própria trilha: `home`, `static`, `ancestor` e `current`.

### 2. Identidade

Na aba **Projeto**, defina:

- **Prefixo.** Vira o nome dos shortcodes. `lrps` gera `[lrps_city_state]`. `phf` gera `[phf_city_state]`. Use `phf` só na Prime House.
- **CPT** e **taxonomia de nível.** O padrão é `location_page` e `seo_level`. Se o site já tem esses nomes, mantenha.
- **Rótulo do ofício.** Exemplo: `Flooring Contractor`. Entra no schema e no título da página de cidade.

Salve o projeto. As rotas são regeneradas nesse carregamento.

### 3. Catálogo

Ainda em **Projeto**:

- **Cidades:** uma por linha, `slug, nome, estado`. O catálogo é fechado. Slug fora dele não gera URL. Slug reservado (`services`, `service-areas`, `blog` e o restante da lista) também não.
- **Serviços:** JSON. `gallery_template_id: 0` esconde a galeria e não pega a galeria de outro serviço. Os IDs de template pertencem a esta instalação do WordPress.

O mesmo slug precisa existir no catálogo, no ACF, na URL e na planilha.

### 4. Campos no ACF

O plugin não cria o grupo. Na aba **Próximos passos**, a tabela **Campos para criar no ACF** lista o field name, o tipo e o que colocar. Crie esses nomes no ACF Pro.

O select de cidade é preenchido pelo catálogo. Não mantenha essa lista na mão. O select de nível usa um valor por nível, por exemplo `level_1_city` e `level_2_service`. O plugin copia essa escolha para o termo da taxonomia.

### 5. Templates

O layout fica no Elementor. Abra a aba **Referência** e use os shortcodes e as Dynamic Tags daquele prefixo.

Regras que evitam quebra:

- Não coloque um shortcode dentro do atributo de outro. O WordPress fecha no primeiro `]`. No texto, use o token `{city_state}` ou `{service_name}`.
- O mapa entra pela Dynamic Tag `prefixo-map-embed-url` ou pelo shortcode `[prefixo_map_embed_url]`. Só vale URL `https` de `google.com/maps/embed` ou `www.google.com/maps/embed`.
- O schema entra uma vez, no fim do template, pelo shortcode do nível. Não coloque o JSON-LD no `wp_head`.
- A condição de exibição do Theme Builder continua escolhendo o template. A opção de priorizar template só reordena um candidato que o Elementor já tenha listado.
- `[section_label]` imprime a classe no próprio título. Na Prime House a classe é `ph-section-label`, porque o CSS do design system já estiliza essa classe.

### 6. Planilha e importação

Na aba **Próximos passos**, copie o CSV de exemplo. A coluna `unique_key` é o identificador único do WP All Import.

- Página de cidade: `{city_slug}__city`, por exemplo `greenwich__city`.
- Página de serviço: `{city_slug}__service__{service_slug}`, por exemplo `greenwich__service__tile-installation`.

A mesma chave na reimportação atualiza o post. Uma chave nova cria outro post e deixa a URL antiga no ar. A linha de exemplo nasce como `draft` de propósito.

`page_level` e `level_term` precisam ser os valores do nível, não o nome bonito da tela.

Faça um piloto com poucas linhas. Abra cada URL, o breadcrumb, o canonical e o schema. Corrija o modelo antes do lote. A aba **Diagnóstico** precisa estar sem bloqueio.

### 7. Yoast

O Yoast continua dono do título. O padrão da operação é:

```
%%title%% %%page%% %%sep%% %%sitename%%
```

O canonical da location page aponta para a URL programática. O schema da página localizada é um `Service` com `areaServed`. Não publique um `LocalBusiness` por cidade. O schema só sai quando a página tem cidade e estado. Uma página só de serviço não inventa área atendida.

O endereço postal, quando existe, sai na entidade da empresa e só fora das páginas locais. Use o endereço real da sede.

## Prime House

Para repetir a instalação de referência:

1. Importe `examples/prime-house-flooring.json` na aba **Projeto**.
2. Desative os snippets PHP de URL, shortcode, schema, sitemap HTML, header do Kadence e registro de CPT. A lista está no `README.md`.
3. Deixe ativos o CSS de breadcrumb, o CSS do `ph-section-label`, o botão de ligação e o rodapé.
4. Não refaça os templates 2172 e 2173. Eles já chamam `[phf_city_state]`, `[phf_faq_question index="1"]` e as tags `phf-location-url`, `phf-service-hero-image` e `phf-service-cover-image`.

## Quando a URL responde 404

A rota reconhece o catálogo, mas a página só abre se existir exatamente um registro publicado daquele nível, cidade e serviço. Combinação duplicada também vira 404, em vez de escolher um dos posts. **Sincronizar níveis** na aba Diagnóstico copia o select para a taxonomia e não mexe no conteúdo.

## Texto localizado

Cada linha cobre uma combinação que o cliente atende de verdade. Não invente endereço, filial, equipe ou prova. Cidade atendida não vira endereço de filial.
