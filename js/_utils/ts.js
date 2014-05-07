/* FROM: https://github.com/civicrm/civicrm-core/pull/3140/files */
/** provides a local copy of ts for a domain */
CRM.ts = function(domain) {
  return function(message, options) {
    if (domain) {
      options = _.extend(options || {}, {domain: domain});
    }
    return ts(message, options);
  };
};