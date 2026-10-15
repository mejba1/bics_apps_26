<tr class="{{ $item->is_active ? '' : 'table-secondary text-muted' }}">
  <td class="{{ $item->parent_id ? 'ps-4' : 'fw-bold' }}">
    @if($item->parent_id)<i class="bi bi-arrow-return-right text-muted"></i>@endif
    {{ $item->title }}
  </td>
  <td><a href="{{ $item->href }}" target="_blank" class="small text-break">{{ $item->url }}</a>@if($item->new_tab) <i class="bi bi-box-arrow-up-right small" title="Opens in new tab"></i>@endif</td>
  <td class="text-center text-nowrap">
    <form method="POST" action="{{ route('menu.move', [$item, 'up']) }}" class="d-inline">@csrf
      <button class="btn btn-outline-secondary btn-sm" title="Move up" @disabled($first)><i class="bi bi-arrow-up"></i></button>
    </form>
    <form method="POST" action="{{ route('menu.move', [$item, 'down']) }}" class="d-inline">@csrf
      <button class="btn btn-outline-secondary btn-sm" title="Move down" @disabled($last)><i class="bi bi-arrow-down"></i></button>
    </form>
  </td>
  <td class="text-center">
    <form method="POST" action="{{ route('menu.toggle', $item) }}" class="d-inline">@csrf
      <button class="btn btn-sm {{ $item->is_active ? 'btn-success' : 'btn-outline-secondary' }}" title="{{ $item->is_active ? 'Hide' : 'Show' }}">
        <i class="bi {{ $item->is_active ? 'bi-eye' : 'bi-eye-slash' }}"></i>
      </button>
    </form>
  </td>
  <td class="text-end text-nowrap">
    <button type="button" class="btn btn-primary btn-sm edit-menu" data-bs-toggle="modal" data-bs-target="#editMenuModal"
      data-action="{{ route('menu.update', $item) }}" data-id="{{ $item->id }}" data-title="{{ $item->title }}" data-url="{{ $item->url }}"
      data-parent="{{ $item->parent_id }}" data-active="{{ $item->is_active ? 1 : 0 }}" data-newtab="{{ $item->new_tab ? 1 : 0 }}" title="Edit">
      <i class="ri-edit-box-line"></i>
    </button>
    <form method="POST" action="{{ route('menu.destroy', $item) }}" class="d-inline"
      onsubmit="return confirm('Delete “{{ addslashes($item->title) }}”{{ $item->children->isNotEmpty() ? ' and its sub-menu' : '' }}?')">
      @csrf @method('DELETE')
      <button class="btn btn-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
    </form>
  </td>
</tr>
