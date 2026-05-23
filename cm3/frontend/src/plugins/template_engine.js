
// Template engine

export function getValueByPath(input, s) {
  if (!s) return undefined;

  let fallbackString = undefined;

  // Check if a fallback pipeline exists: path || 'fallback'
  if (s.includes('||')) {
    const segments = s.split('||');
    s = segments[0].trim(); // Extract the true path (e.g., "profile.firstName")
    
    // Clean up the fallback string by stripping spaces and wrapping quotes (' or ")
    fallbackString = segments[1].trim().replace(/^['"]|['"]$/g, '');
  }

  s = s.replace(/\[(\w+)\]/g, '.$1'); 
  s = s.replace(/^\./, ''); 
  var a = s.split('.');
  let current = input;

  for (var i = 0, n = a.length; i < n; ++i) {
    var k = a[i];
    if ((current === Object(current)) && k in current) {
      current = current[k];
    } else {
      // If the property path fails but a fallback string exists, return the fallback instead of undefined
      return fallbackString !== undefined ? fallbackString : undefined;
    }
  }

  // If the path evaluates to a falsy/blank value, honor the fallback string if it exists
  if (current === undefined || current === null || current === '') {
    return fallbackString !== undefined ? fallbackString : current;
  }

  return current;
}


export function compileTemplate(template, templateData) {
  // Tokenize the string by splitting on all [[ tags ]]
  const tagRegex = /(\[\[[\s\S]*?\]\])/g;
  const parts = template.split(tagRegex);
  
  let index = 0;

  // Global helper function to skip sections until reaching matching boundaries
  function swallowBlock() {
    let depth = 1;
    while (index < parts.length && depth > 0) {
      const p = parts[index++];
      if (p.startsWith('[[')) {
        const tag = p.slice(2, -2).trim();
        if (tag.startsWith('for ') || tag.startsWith('if ')) depth++;
        if (tag === 'end') depth--;
      }
    }
  }

  function parseBlock(context) {
    let result = '';

    function isTruthy(value) {
      if (!value) return false;
      if (Array.isArray(value) && value.length === 0) return false;
      return true;
    }
    while (index < parts.length) {
      const part = parts[index++];

      // Handle normal markdown and text syntax fragments
      if (!part.startsWith('[[')) {
        result += part;
        continue;
      }

      const innerContent = part.slice(2, -2).trim();

      // Handle structural closing loops
      if (innerContent === 'end') {
        return result;
      }

      // If we see an raw [[else]], it means the main branch finished executing successfully.
      // Therefore, we must skip the else container contents completely.
      if (innerContent === 'else') {
        swallowBlock();
        return result;
      }

      // Handle structural conditions: [[if path]]
      if (innerContent.startsWith('if ')) {
        const path = innerContent.substring(3).trim();
        const value = getValueByPath(context, path);
        
        if (value === undefined) {
          console.warn(`[Template Debug] Property not found along condition path: "${path}"`, context);
          // If the variable is completely missing, write the literal missing fallback pattern into the output string 
          // and swallow the internal conditional layout block completely.
          result += `[[?${path}]]`;
          swallowBlock();
          continue;
        }

        if (isTruthy(value)) {
          result += parseBlock(context);
        } else {
          // Search loop sequentially for a structural fallback branch
          let depth = 1;
          let foundElse = false;
          while (index < parts.length && depth > 0) {
            const p = parts[index++];
            if (p.startsWith('[[')) {
              const tag = p.slice(2, -2).trim();
              if (tag.startsWith('for ') || tag.startsWith('if ')) depth++;
              if (tag === 'end') depth--;
              if (tag === 'else' && depth === 1) {
                foundElse = true;
                break;
              }
            }
          }
          if (foundElse) {
            result += parseBlock(context); // Run the else branch block
          }
        }
        continue;
      }

      // Handle iteration loop syntax block: [[for path]]
      if (innerContent.startsWith('for ')) {
        const path = innerContent.substring(4).trim();
        const list = getValueByPath(context, path);
        
        if (list === undefined) {
          console.warn(`[Template Debug] Loop array target not found at path: "${path}"`, context);
          // Append the fallback string format and swallow the structural loop block entirely
          result += `[[?${path}]]`;
          swallowBlock();
          continue;
        }

        const loopBodyStartIndex = index;

        if (Array.isArray(list) && list.length > 0) {
          list.forEach((item) => {
            index = loopBodyStartIndex; // Snap text reading pointer back to start of loop content
            const scopedContext = (typeof item === 'object' && item !== null) ? { ...item } : { value: item };
            result += parseBlock(scopedContext);
          });

          // Once items execute, skip over the accompanying trailing [[else]] block container
          index = loopBodyStartIndex;
          swallowBlock();
        } else {
          // Empty or falsy lists navigate straight down to the [[else]] branch
          let depth = 1;
          let foundElse = false;
          while (index < parts.length && depth > 0) {
            const p = parts[index++];
            if (p.startsWith('[[')) {
              const tag = p.slice(2, -2).trim();
              if (tag.startsWith('for ') || tag.startsWith('if ')) depth++;
              if (tag === 'end') depth--;
              if (tag === 'else' && depth === 1) {
                foundElse = true;
                break;
              }
            }
          }
          if (foundElse) {
            result += parseBlock(context);
          }
        }
        continue;
      }

      // Handle standalone string output fields: [[variable]]
      const value = getValueByPath(context, innerContent);
      if (value === undefined) {
        console.warn(`[Template Debug] Variable target not found: "[[${innerContent}]]"`, context);
        // Swap out blank lines for the explicit missing paths tracking placeholder
        result += `[[?${innerContent}]]`; 
      } else {
        result += String(value);
      }
    }

    return result;
  }

  return parseBlock(templateData);
}

// end template engine
