{{-- Compat: delega ao seletor compartilhado Buriti --}}
@include('buriti-ui::select-usuario', [
    'campo' => $campo ?? 'usuario_id',
    'rotulo' => $rotulo ?? 'Pessoa',
    'obrigatorio' => $obrigatorio ?? false,
    'usuarios' => $usuarios ?? $opcoes ?? [],
    'selecionado' => $selecionado ?? null,
    'ajuda' => $ajuda ?? null,
    'placeholder' => $placeholder ?? 'Buscar por nome ou matrícula…',
    'vazio' => $vazio ?? '— selecionar na lista —',
    'icone' => $icone ?? 'fa-user',
])
