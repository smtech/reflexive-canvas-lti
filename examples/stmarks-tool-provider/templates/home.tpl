{extends file="subpage.tpl"}

{block name="subcontent"}

    <div class="container">
        <pre>{var_export($profile, true)}</pre>
    </div>

{/block}
