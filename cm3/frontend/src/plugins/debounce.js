export function debounce(fn, delay, options = {}) {
  let timeoutID = null;
  let lastArgs = null;
  let lastThis = null;
  let result;

  const leading = !!options.leading;
  // Default trailing to true if not explicitly set to false
  const trailing = options.trailing !== undefined ? options.trailing : true;

  function later() {
    timeoutID = null;
    // Execute trailing edge if enabled and we have pending calls during the delay
    if (trailing && lastArgs) {
      result = fn.apply(lastThis, lastArgs);
      lastArgs = lastThis = null;
    }
  }

  return function (...args) {
    lastArgs = args;
    lastThis = this;

    const isInvoking = !timeoutID;

    // Reset the timer on every call
    if (timeoutID) {
      clearTimeout(timeoutID);
    }

    timeoutID = setTimeout(later, delay);

    // Handle leading edge
    if (isInvoking && leading) {
      result = fn.apply(lastThis, lastArgs);
      // Clear lastArgs so trailing doesn't fire a duplicate 
      // unless a new call happens during the delay window
      lastArgs = lastThis = null;
    }

    return result;
  };
}