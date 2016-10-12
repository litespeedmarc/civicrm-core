{if $priceSetId}
  {include file="CRM/Price/Form/PriceSet.tpl" context="standalone" extends="Membership"}
  <script>
    {if $price_set_has_new_orgs}
      cj('#join_date_desc').show();
    {else}
      cj('#join_date_desc').hide();
    {/if}
  </script>
{/if}
