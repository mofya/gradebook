@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<span style="font-size: 22px; font-weight: bold; color: #92400e;">📚 {!! $slot !!}</span>
</a>
</td>
</tr>
