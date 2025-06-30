(function (Drupal) {
  Drupal.behaviors.cookieToken = {
    attach(context) {
      const inputs =  once('w2w2l', 'input[value^="[w2w2l:cookie"', context)
      inputs.forEach((input) => {
        input.value = input.value.replaceAll(/\[w2w2l:cookie:([^\]]+)\]/g, this.cookieValue)
      });
    },

    cookieValue(match, token) {
      return document.cookie
        .split("; ")
        .find((row) => row.startsWith(`w2w2l-${token}=`))
        ?.split("=")[1] || ''
    }
  };
})(Drupal, drupalSettings);
