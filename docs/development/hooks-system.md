# Sistema de Hooks do Cobra AI

## Visão Geral

O Cobra AI utiliza um sistema de hooks modulares do WordPress para permitir que as funcionalidades se integrem de forma limpa nos formulários principais.

## Hooks Disponíveis

### Formulários de Autenticação

#### `cobra_before_login_form`
- **Localização**: `features/register/views/forms/login.php`
- **Descrição**: Executado antes do formulário de login
- **Uso**: Permite que funcionalidades como AuthGoogle adicionem botões de login alternativo

#### `cobra_before_register_form`
- **Localização**: `features/register/views/forms/register.php`
- **Descrição**: Executado antes do formulário de registro
- **Uso**: Permite que funcionalidades adicionem opções de registro alternativo

## Como Implementar uma Funcionalidade com Hooks

### Exemplo: Funcionalidade AuthGoogle

#### 1. Configuração no `setup()`

```php
protected function init_hooks(): void
{
    parent::init_hooks();
    
    // Inicializar hooks dos formulários Cobra AI
    $this->init_cobra_form_hooks();
}

protected function init_cobra_form_hooks(): void
{
    $settings = $this->get_settings();
    
    // Hook no formulário de login se habilitado
    if (!empty($settings['display']['show_on_login'])) {
        add_action('cobra_before_login_form', [$this, 'render_google_login_for_cobra_forms']);
    }
    
    // Hook no formulário de registro se habilitado
    if (!empty($settings['display']['show_on_register'])) {
        add_action('cobra_before_register_form', [$this, 'render_google_login_for_cobra_forms']);
    }
}
```

#### 2. Método de Renderização

```php
public function render_google_login_for_cobra_forms(): void
{
    if (!$this->is_google_configured()) {
        return;
    }
    
    echo '<div class="cobra-google-auth-wrapper">';
    echo do_shortcode('[cobra_google_login]');
    echo '</div>';
}
```

#### 3. Configuração nas Opções

```php
protected function get_feature_default_options(): array
{
    return [
        // ... outras opções
        'display' => [
            'show_on_login' => true,
            'show_on_register' => true,
            'show_on_wordpress_login' => false,
            'show_on_woocommerce' => false,
        ]
    ];
}
```

## Vantagens do Sistema de Hooks

### 1. **Modularidade**
- Cada funcionalidade é independente
- Pode ser ativada/desativada sem afetar outras

### 2. **Configurabilidade**
- Administradores podem escolher onde mostrar cada funcionalidade
- Interface de configuração simples e intuitiva

### 3. **Extensibilidade**
- Fácil adicionar novos hooks conforme necessário
- Terceiros podem estender o sistema

### 4. **Performance**
- Hooks só são executados quando necessário
- Código só é carregado se a funcionalidade estiver ativa

## Padrões de Nomenclatura

### Hooks de Ação
- `cobra_before_{form_name}_form` - Antes do formulário
- `cobra_after_{form_name}_form` - Depois do formulário
- `cobra_in_{form_name}_form` - Dentro do formulário

### Hooks de Filtro
- `cobra_{form_name}_form_fields` - Modificar campos do formulário
- `cobra_{form_name}_form_validation` - Validação customizada

## Implementação em Templates

### Antes (Código Estático)
```php
<?php if ($is_feature_authGoogle_enabled): ?> 
    <?php echo do_shortcode('[cobra_google_login]'); ?>
<?php endif; ?>
```

### Depois (Sistema de Hooks)
```php
<?php
// Hook para integrações de terceiros (ex: Google Auth)
do_action('cobra_before_login_form');
?>
```

## Funcionalidades que Usam Hooks

### AuthGoogle
- **Hook**: `cobra_before_login_form`, `cobra_before_register_form`
- **Configuração**: `settings['display']['show_on_login']`, `settings['display']['show_on_register']`
- **Método**: `render_google_login_for_cobra_forms()`

### Extensões Futuras
O sistema está preparado para suportar:
- Integração com Facebook Login
- Autenticação via Apple ID
- Sistemas de SSO corporativo
- Integrações customizadas

## Melhores Práticas

1. **Sempre verificar configuração** antes de renderizar
2. **Usar wrapper HTML** para facilitar estilização
3. **Implementar fallbacks** quando serviços externos estão indisponíveis
4. **Documentar hooks customizados** para desenvolvedores terceiros
5. **Testar com múltiplas funcionalidades** ativas simultaneamente